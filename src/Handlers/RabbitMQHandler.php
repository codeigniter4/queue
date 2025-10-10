<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Queue.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Queue\Handlers;

use CodeIgniter\Exceptions\CriticalError;
use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Events\QueueEventManager;
use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use CodeIgniter\Queue\QueuePushResult;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMQHandler extends BaseHandler implements QueueInterface
{
    private readonly AbstractConnection $connection;
    private readonly AMQPChannel $channel;
    private array $declaredQueues    = [];
    private array $declaredExchanges = [];

    public function __construct(protected QueueConfig $config)
    {
        try {
            $amqp = new AMQPConnectionConfig();
            $amqp->setHost($config->rabbitmq['host']);
            $amqp->setPort($config->rabbitmq['port']);
            $amqp->setUser($config->rabbitmq['user']);
            $amqp->setPassword($config->rabbitmq['password']);
            $amqp->setVhost($config->rabbitmq['vhost'] ?? '/');

            // Enable SSL/TLS
            if ($config->rabbitmq['ssl'] ?? ($config->rabbitmq['port'] === 5671)) {
                $amqp->setIsSecure(true);
            }

            $this->connection = AMQPConnectionFactory::create($amqp);
            $this->channel    = $this->connection->channel();

            // Set QoS for consumer (prefetch limit)
            $this->channel->basic_qos(0, 1, false);

            // Enable publisher confirms if configured
            if ($config->rabbitmq['publisherConfirms'] ?? false) {
                $this->channel->confirm_select();
            }

            // Register return handler for unroutable messages
            $this->channel->set_return_listener(static function ($replyCode, $replyText, $exchange, $routingKey, $properties, $body): void {
                log_message('error', "RabbitMQ returned unroutable message: {$replyCode} {$replyText} exchange={$exchange} routing_key={$routingKey}");
            });

            // Emit connection established event
            QueueEventManager::handlerConnectionEstablished(
                handler: $this->name(),
                config: $config->rabbitmq,
            );
        } catch (Throwable $e) {
            // Emit connection failed event
            QueueEventManager::handlerConnectionFailed(
                handler: $this->name(),
                exception: $e,
                config: $config->rabbitmq,
            );

            throw new CriticalError('Queue: RabbitMQ connection failed. ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        try {
            $this->channel->close();
            $this->connection->close();
        } catch (Throwable) {
            // Ignore connection cleanup errors
        }
    }

    /**
     * Name of the handler.
     */
    public function name(): string
    {
        return 'rabbitmq';
    }

    /**
     * Add job to the queue.
     */
    public function push(string $queue, string $job, array $data, ?PayloadMetadata $metadata = null): QueuePushResult
    {
        $this->validateJobAndPriority($queue, $job);

        try {
            helper('text');
            $jobId = (int) random_string('numeric', 16);

            $queueJob = new QueueJob([
                'id'           => $jobId,
                'queue'        => $queue,
                'payload'      => new Payload($job, $data, $metadata),
                'priority'     => $this->priority,
                'status'       => Status::PENDING->value,
                'attempts'     => 0,
                'available_at' => Time::now()->addSeconds($this->delay ?? 0),
            ]);

            $this->declareQueue($queue);
            $this->declareExchange($queue);

            $routingKey = $this->getRoutingKey($queue, $this->priority);

            if ($this->delay !== null && $this->delay > 0) {
                // Calculate delay based on available_at time using consistent time source
                $targetTime  = $queueJob->available_at->getTimestamp();
                $currentTime = Time::now()->getTimestamp();
                $realDelay   = $targetTime - $currentTime;

                if ($realDelay <= 0) {
                    // No delay needed or already past due - publish immediately
                    $message = $this->createMessage($queueJob);
                    $this->publishMessage($queue, $message, $routingKey);
                } else {
                    // Use TTL + dead letter pattern for actual delays
                    $this->publishDelayedMessage($queue, $queueJob, $routingKey, $realDelay);
                }
            } else {
                $message = $this->createMessage($queueJob);
                $this->publishMessage($queue, $message, $routingKey);
            }

            $this->priority = $this->delay = null;

            // Emit job pushed event
            QueueEventManager::jobPushed(
                handler: $this->name(),
                queue: $queue,
                job: $queueJob,
            );

            return QueuePushResult::success($jobId);
        } catch (Throwable $e) {
            // Emit push failed event
            QueueEventManager::jobPushFailed(
                handler: $this->name(),
                queue: $queue,
                jobClass: $job,
                exception: $e,
            );

            return QueuePushResult::failure($e->getMessage());
        }
    }

    /**
     * Get next job from queue.
     */
    public function pop(string $queue, array $priorities): ?QueueJob
    {
        try {
            $this->declareQueue($queue);

            // Try to get message with priorities in order
            foreach ($priorities as $priority) {
                $queueName = $this->getQueueName($queue, $priority);
                $message   = $this->channel->basic_get($queueName, false);

                if ($message !== null) {
                    return $this->messageToQueueJob($message);
                }
            }

            return null;
        } catch (Throwable $e) {
            log_message('error', 'RabbitMQ pop error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Reschedule job to run later.
     */
    public function later(QueueJob $queueJob, int $seconds): bool
    {
        try {
            $queueJob->status       = Status::PENDING->value;
            $queueJob->available_at = Time::now()->addSeconds($seconds);

            // Reject the original message without requeue
            if (isset($queueJob->amqpDeliveryTag)) {
                $this->channel->basic_nack($queueJob->amqpDeliveryTag, false, false);
            }

            $routingKey = $this->getRoutingKey($queueJob->queue, $queueJob->priority);

            $this->publishDelayedMessage($queueJob->queue, $queueJob, $routingKey, $seconds);

            return true;
        } catch (Throwable $e) {
            log_message('error', 'RabbitMQ later error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Handle failed job.
     */
    public function failed(QueueJob $queueJob, Throwable $err, bool $keepJob): bool
    {
        try {
            // Reject the message without requeue
            if (isset($queueJob->amqpDeliveryTag)) {
                $this->channel->basic_nack($queueJob->amqpDeliveryTag, false, false);
            }

            if ($keepJob) {
                $this->logFailed($queueJob, $err);
            }

            return true;
        } catch (Throwable $e) {
            log_message('error', 'RabbitMQ failed error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Mark job as completed.
     */
    public function done(QueueJob $queueJob): bool
    {
        try {
            // Acknowledge the message to remove it from the queue
            if (isset($queueJob->amqpDeliveryTag)) {
                $this->channel->basic_ack($queueJob->amqpDeliveryTag);
            }

            return true;
        } catch (Throwable $e) {
            log_message('error', 'RabbitMQ done error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Clear all jobs from queue(s).
     */
    public function clear(?string $queue = null): bool
    {
        try {
            if ($queue === null) {
                // Clear all configured queues
                foreach (array_keys($this->config->queuePriorities) as $queueName) {
                    $this->clearQueue($queueName);
                }
            } else {
                $this->clearQueue($queue);
            }

            // Emit queue cleared event
            QueueEventManager::queueCleared(
                handler: $this->name(),
                queue: $queue,
            );

            return true;
        } catch (Throwable $e) {
            log_message('error', 'RabbitMQ clear error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Declare queue with priority support.
     */
    private function declareQueue(string $queue): void
    {
        $priorities = $this->config->queuePriorities[$queue] ?? ['default'];

        foreach ($priorities as $priority) {
            $queueName = $this->getQueueName($queue, $priority);

            if (! isset($this->declaredQueues[$queueName])) {
                $this->channel->queue_declare(
                    $queueName,
                    false,
                    true,
                    false,
                    false,
                );

                $this->declaredQueues[$queueName] = true;
            }
        }
    }

    /**
     * Declare exchange for queue routing.
     */
    private function declareExchange(string $queue): void
    {
        $exchangeName = $this->getExchangeName($queue);

        if (! isset($this->declaredExchanges[$exchangeName])) {
            $this->channel->exchange_declare(
                $exchangeName,
                'direct',
                false,
                true,
                false,
            );

            $this->declaredExchanges[$exchangeName] = true;
        }

        // Bind queues to exchanges
        $priorities = $this->config->queuePriorities[$queue] ?? ['default'];

        foreach ($priorities as $priority) {
            $queueName  = $this->getQueueName($queue, $priority);
            $routingKey = $this->getRoutingKey($queue, $priority);

            $this->channel->queue_bind($queueName, $exchangeName, $routingKey);
        }
    }

    /**
     * Create AMQP message from QueueJob with optional additional properties.
     */
    private function createMessage(QueueJob $queueJob, array $additionalProperties = []): AMQPMessage
    {
        $body = json_encode($queueJob->toArray());
        if ($body === false) {
            throw QueueException::forFailedJsonEncode(json_last_error_msg());
        }

        $properties = array_merge([
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'timestamp'     => Time::now()->getTimestamp(),
            'content_type'  => 'application/json',
            'message_id'    => (string) $queueJob->id,
        ], $additionalProperties);

        return new AMQPMessage($body, $properties);
    }

    /**
     * Convert AMQP message to QueueJob.
     */
    private function messageToQueueJob(AMQPMessage $message): QueueJob
    {
        $data = json_decode($message->getBody(), true);

        $queueJob = new QueueJob($data);

        // Mark message as acknowledged but not deleted yet
        // We'll ack it when done() is called
        $queueJob->amqpDeliveryTag = $message->getDeliveryTag();

        // Update the job status
        $queueJob->status = Status::RESERVED->value;
        $queueJob->syncOriginal();

        return $queueJob;
    }

    /**
     * Publish message with delay using per-message TTL + dead letter pattern.
     */
    private function publishDelayedMessage(string $queue, QueueJob $queueJob, string $routingKey, int $delaySeconds): void
    {
        $delayQueueName = $this->getDelayQueueName($queue);
        $exchangeName   = $this->getExchangeName($queue); // Use the main exchange

        // Declare single delay queue (without queue-level TTL)
        if (! isset($this->declaredQueues[$delayQueueName])) {
            $this->channel->queue_declare(
                $delayQueueName,
                false,
                true,
                false,
                false,
                false,
                new AMQPTable([
                    'x-dead-letter-exchange'    => $exchangeName,
                    'x-dead-letter-routing-key' => $routingKey,
                ]),
            );

            $this->declaredQueues[$delayQueueName] = true;
        }

        // Bind delay queue to main exchange with delay routing key
        $this->channel->queue_bind($delayQueueName, $exchangeName, $delayQueueName);

        // Create message with per-message expiration (milliseconds string)
        $delayedMessage = $this->createMessage($queueJob, [
            'expiration' => (string) ($delaySeconds * 1000),
        ]);

        $this->publishWithOptionalConfirm($delayedMessage, $exchangeName, $delayQueueName);
    }

    /**
     * Publish message immediately.
     */
    private function publishMessage(string $queue, AMQPMessage $message, string $routingKey): void
    {
        $exchangeName = $this->getExchangeName($queue);
        $this->publishWithOptionalConfirm($message, $exchangeName, $routingKey);
    }

    /**
     * Publish message with optional publisher confirms and mandatory delivery.
     */
    private function publishWithOptionalConfirm(AMQPMessage $message, string $exchange, string $routingKey): void
    {
        // Publish with mandatory=true to prevent silent drops if routing fails
        $this->channel->basic_publish($message, $exchange, $routingKey, true);

        if ($this->config->rabbitmq['publisherConfirms'] ?? false) {
            try {
                $this->channel->wait_for_pending_acks_returns();
            } catch (Throwable $e) {
                log_message('error', 'RabbitMQ publish confirm failure: ' . $e->getMessage());

                throw $e; // Re-throw to fail the operation
            }
        }
    }

    /**
     * Clear the specific queue.
     */
    private function clearQueue(string $queue): void
    {
        $priorities = $this->config->queuePriorities[$queue] ?? ['default'];

        foreach ($priorities as $priority) {
            $queueName = $this->getQueueName($queue, $priority);

            try {
                $this->channel->queue_purge($queueName);
            } catch (Throwable) {
                // Queue might not exist, ignore
            }
        }
    }

    /**
     * Get queue name with priority suffix.
     */
    private function getQueueName(string $queue, string $priority): string
    {
        return $priority === 'default' ? $queue : "{$queue}_{$priority}";
    }

    /**
     * Get exchange name for queue.
     */
    private function getExchangeName(string $queue): string
    {
        return "queue_{$queue}_exchange";
    }

    /**
     * Get delay queue name (single queue per logical queue).
     */
    private function getDelayQueueName(string $queue): string
    {
        return "queue_{$queue}_delay";
    }

    /**
     * Get routing key for priority.
     */
    private function getRoutingKey(string $queue, string $priority): string
    {
        return "{$queue}.{$priority}";
    }
}
