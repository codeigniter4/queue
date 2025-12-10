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

use CodeIgniter\Autoloader\FileLocator;
use CodeIgniter\Exceptions\CriticalError;
use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Events\QueueEventManager;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use CodeIgniter\Queue\QueuePushResult;
use Exception;
use Predis\Client;
use RuntimeException;
use Throwable;

class PredisHandler extends BaseHandler implements QueueInterface
{
    private readonly Client $predis;
    private readonly string $luaScript;

    public function __construct(protected QueueConfig $config)
    {
        try {
            $this->predis = new Client($config->predis, ['prefix' => $config->predis['prefix']]);
            $this->predis->time();

            $locator   = new FileLocator(service('autoloader'));
            $luaScript = $locator->locateFile('CodeIgniter\Queue\Lua\pop_task', null, 'lua');
            if ($luaScript === false) {
                throw new CriticalError('Queue: LUA script for Predis is not available.');
            }
            $this->luaScript = file_get_contents($luaScript);

            // Emit connection established event
            QueueEventManager::handlerConnectionEstablished(
                handler: $this->name(),
                config: $config->predis,
            );
        } catch (Exception $e) {
            // Emit connection failed event
            QueueEventManager::handlerConnectionFailed(
                handler: $this->name(),
                exception: $e,
                config: $config->predis,
            );

            throw new CriticalError('Queue: Predis connection refused (' . $e->getMessage() . ').');
        }
    }

    /**
     * Name of the handler.
     */
    public function name(): string
    {
        return 'predis';
    }

    /**
     * Add job to the queue.
     */
    public function push(string $queue, string $job, array $data, ?PayloadMetadata $metadata = null): QueuePushResult
    {
        $this->validateJobAndPriority($queue, $job);

        helper('text');

        $jobId       = (int) random_string('numeric', 16);
        $availableAt = Time::now()->addSeconds($this->delay ?? 0);

        $queueJob = new QueueJob([
            'id'           => $jobId,
            'queue'        => $queue,
            'payload'      => new Payload($job, $data, $metadata),
            'priority'     => $this->priority,
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => $availableAt,
        ]);

        try {
            $result = $this->predis->zadd("queues:{$queue}:{$this->priority}", [json_encode($queueJob) => $availableAt->timestamp]);
        } catch (Throwable $e) {
            // Emit push failed event
            QueueEventManager::jobPushFailed(
                handler: $this->name(),
                queue: $queue,
                jobClass: $job,
                exception: $e,
            );

            return QueuePushResult::failure('Unexpected Redis error: ' . $e->getMessage());
        } finally {
            $this->priority = $this->delay = null;
        }

        if ($result > 0) {
            // Emit job pushed event
            QueueEventManager::jobPushed(
                handler: $this->name(),
                queue: $queue,
                job: $queueJob,
            );

            return QueuePushResult::success($jobId);
        }
        $error = new RuntimeException('Job already exists in the queue.');
        QueueEventManager::jobPushFailed(
            handler: $this->name(),
            queue: $queue,
            jobClass: $job,
            exception: $error,
        );

        return QueuePushResult::failure($error->getMessage());
    }

    /**
     * Get job from the queue.
     */
    public function pop(string $queue, array $priorities): ?QueueJob
    {
        $now = (string) Time::now()->timestamp;

        // Prepare the arguments for the Lua script
        $args = [
            'queues:' . $queue,       // KEYS[1]
            $now,                     // ARGV[2]
            json_encode($priorities), // ARGV[3]
        ];

        // Execute the Lua script
        $task = $this->predis->eval($this->luaScript, 1, ...$args);

        if ($task === null) {
            return null;
        }

        $queueJob = new QueueJob(json_decode((string) $task, true));

        // Set the actual status as in DB.
        $queueJob->status = Status::RESERVED->value;
        $queueJob->syncOriginal();

        $this->predis->hset("queues:{$queue}::reserved", (string) $queueJob->id, json_encode($queueJob));

        return $queueJob;
    }

    /**
     * Schedule job for later
     */
    public function later(QueueJob $queueJob, int $seconds): bool
    {
        $queueJob->status       = Status::PENDING->value;
        $queueJob->available_at = Time::now()->addSeconds($seconds);

        $result = $this->predis->zadd(
            "queues:{$queueJob->queue}:{$queueJob->priority}",
            [json_encode($queueJob) => $queueJob->available_at->timestamp],
        );
        if ($result !== 0) {
            $this->predis->hdel("queues:{$queueJob->queue}::reserved", [$queueJob->id]);
        }

        return $result > 0;
    }

    /**
     * Move job to failed table or move and delete.
     */
    public function failed(QueueJob $queueJob, Throwable $err, bool $keepJob): bool
    {
        if ($keepJob) {
            $this->logFailed($queueJob, $err);
        }

        return (bool) $this->predis->hdel("queues:{$queueJob->queue}::reserved", [$queueJob->id]);
    }

    /**
     * Change job status to DONE or delete it.
     */
    public function done(QueueJob $queueJob): bool
    {
        return (bool) $this->predis->hdel("queues:{$queueJob->queue}::reserved", [$queueJob->id]);
    }

    /**
     * Delete queue jobs
     */
    public function clear(?string $queue = null): bool
    {
        if ($queue !== null) {
            $keys   = $this->predis->keys("queues:{$queue}:*");
            $result = $keys !== [] ? $this->predis->del($keys) > 0 : true;
        } else {
            $keys   = $this->predis->keys('queues:*');
            $result = $keys !== [] ? $this->predis->del($keys) > 0 : true;
        }

        if ($result) {
            // Emit queue cleared event
            QueueEventManager::queueCleared(
                handler: $this->name(),
                queue: $queue,
            );
        }

        return $result;
    }
}
