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
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use CodeIgniter\Queue\QueuePushResult;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMQHandler extends BaseHandler
{
    private readonly AbstractConnection $connection;
    private readonly AMQPChannel $channel;
    private array $declaredLogicalQueues = [];
    private array $declaredQueues        = [];
    private array $declaredExchanges     = [];

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
                    $this->publishDelayedMessage($queue, $queueJob, $queueJob->priority, $realDelay);
                }
            } else {
                $message = $this->createMessage($queueJob);
                $this->publishMessage($queue, $message, $routingKey);
            }

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
        } finally {
            // Never leak priority or delay into the next push operation.
            $this->priority = $this->delay = null;
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

            // Publish the delayed copy first. If publishing fails the original
            // message must stay unacknowledged so the job is not lost.
            $this->publishDelayedMessage($queueJob->queue, $queueJob, $queueJob->priority, $seconds);

            // Reject the original message only after the replacement exists.
            if (isset($queueJob->amqpDeliveryTag)) {
                $this->channel->basic_nack($queueJob->amqpDeliveryTag, false, false);
            }

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
                $queueNames = array_unique([
                    ...array_keys($this->config->queuePriorities),
                    ...array_keys($this->declaredLogicalQueues),
                ]);

                foreach ($queueNames as $queueName) {
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
        $this->declaredLogicalQueues[$queue] = true;
        $priorities                          = $this->config->queuePriorities[$queue] ?? ['default'];

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

            // Bind queues to the exchange only once, together with the
            // declaration. Re-binding on every publish is unnecessary traffic.
            $priorities = $this->config->queuePriorities[$queue] ?? ['default'];

            foreach ($priorities as $priority) {
                $queueName  = $this->getQueueName($queue, $priority);
                $routingKey = $this->getRoutingKey($queue, $priority);

                $this->channel->queue_bind($queueName, $exchangeName, $routingKey);
            }

            $this->declaredExchanges[$exchangeName] = true;
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
     *
     * Delay queues are separated per priority so the dead-letter routing key
     * always points at the priority bucket the job belongs to.
     */
    private function publishDelayedMessage(string $queue, QueueJob $queueJob, string $priority, int $delaySeconds): void
    {
        // Make sure the exchange exists. later() may be called by a worker that
        // never went through push(), so we cannot rely on push() declaring it.
        $this->declareExchange($queue);

        $delayQueueName = $this->getDelayQueueName($queue, $priority);
        $exchangeName   = $this->getExchangeName($queue); // Use the main exchange
        $routingKey     = $this->getRoutingKey($queue, $priority);

        // Declare and bind delay queue for this priority (without queue-level TTL).
        // Binding only happens on the first publish for this delay queue.
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

            $this->channel->queue_bind($delayQueueName, $exchangeName, $delayQueueName);

            $this->declaredQueues[$delayQueueName] = true;
        }

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
            // Purge delayed jobs first so they cannot expire into a queue
            // that has already been purged.
            $this->purgeQueueIfExists($this->getDelayQueueName($queue, $priority));
            $this->purgeQueueIfExists($this->getQueueName($queue, $priority));
        }

        // Purge the pre-priority delay queue so jobs scheduled before the
        // per-priority delay queues were introduced cannot leak after a clear.
        $this->purgeQueueIfExists($this->getLegacyDelayQueueName($queue));
    }

    /**
     * Purge a queue without risking the handler's main channel.
     */
    private function purgeQueueIfExists(string $queue): void
    {
        $channel = $this->connection->channel();

        try {
            $channel->queue_purge($queue);
        } catch (Throwable $e) {
            // RabbitMQ closes the channel with 404 when the queue is missing.
            if (! $e instanceof AMQPProtocolChannelException || $e->getCode() !== 404) {
                throw $e;
            }
        } finally {
            try {
                if ($channel->is_open()) {
                    $channel->close();
                }
            } catch (Throwable) {
                // The broker may already have closed this disposable channel.
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
     * Get delay queue name (one queue per logical queue and priority).
     */
    private function getDelayQueueName(string $queue, string $priority): string
    {
        return "queue_{$queue}_{$priority}_delay";
    }

    /**
     * Get the legacy delay queue name (before per-priority delay queues).
     */
    private function getLegacyDelayQueueName(string $queue): string
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
