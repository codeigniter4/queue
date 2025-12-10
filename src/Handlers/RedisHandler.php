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
use Redis;
use RedisException;
use RuntimeException;
use Throwable;

class RedisHandler extends BaseHandler implements QueueInterface
{
    private readonly Redis $redis;
    private readonly string $luaScript;

    public function __construct(protected QueueConfig $config)
    {
        $this->redis = new Redis();

        try {
            if (! $this->redis->connect($config->redis['host'], ($config->redis['host'][0] === '/' ? 0 : $config->redis['port']), $config->redis['timeout'])) {
                throw new CriticalError('Queue: Redis connection failed. Check your configuration.');
            }
            if (isset($config->redis['username'], $config->redis['password']) && ! $this->redis->auth([$config->redis['username'], $config->redis['password']])) {
                throw new CriticalError('Queue: Redis authentication failed. Check your username and password.');
            }

            if (isset($config->redis['password']) && ! $this->redis->auth($config->redis['password'])) {
                throw new CriticalError('Queue: Redis authentication failed. Check your password.');
            }

            if (isset($config->redis['database']) && ! $this->redis->select($config->redis['database'])) {
                throw new CriticalError('Queue: Redis select database failed.');
            }

            if (isset($config->redis['prefix']) && ! $this->redis->setOption(Redis::OPT_PREFIX, $config->redis['prefix'])) {
                throw new CriticalError('Queue: Redis setting prefix failed.');
            }

            $locator   = new FileLocator(service('autoloader'));
            $luaScript = $locator->locateFile('CodeIgniter\Queue\Lua\pop_task', null, 'lua');
            if ($luaScript === false) {
                throw new CriticalError('Queue: LUA script for Redis is not available.');
            }
            $this->luaScript = file_get_contents($luaScript);

            // Emit connection established event
            QueueEventManager::handlerConnectionEstablished(
                handler: $this->name(),
                config: $config->redis,
            );
        } catch (RedisException $e) {
            // Emit connection failed event
            QueueEventManager::handlerConnectionFailed(
                handler: $this->name(),
                exception: $e,
                config: $config->redis,
            );

            throw new CriticalError('Queue: RedisException occurred with message (' . $e->getMessage() . ').');
        }
    }

    /**
     * Name of the handler.
     */
    public function name(): string
    {
        return 'redis';
    }

    /**
     * Add job to the queue.
     *
     * @throws RedisException
     */
    public function push(string $queue, string $job, array $data, ?PayloadMetadata $metadata = null): QueuePushResult
    {
        $this->validateJobAndPriority($queue, $job);

        helper('text');

        $availableAt = Time::now()->addSeconds($this->delay ?? 0);
        $jobId       = (int) random_string('numeric', 16);

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
            $result = $this->redis->zAdd("queues:{$queue}:{$this->priority}", $availableAt->timestamp, json_encode($queueJob));
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

        if ($result === false) {
            $error = new RuntimeException('Failed to add job to Redis.');
            QueueEventManager::jobPushFailed(
                handler: $this->name(),
                queue: $queue,
                jobClass: $job,
                exception: $error,
            );

            return QueuePushResult::failure($error->getMessage());
        }

        if ((int) $result > 0) {
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
     *
     * @throws RedisException
     */
    public function pop(string $queue, array $priorities): ?QueueJob
    {
        $now = Time::now()->timestamp;

        // Prepare the arguments for the Lua script
        $args = [
            'queues:' . $queue,       // KEYS[1]
            $now,                     // ARGV[2]
            json_encode($priorities), // ARGV[3]
        ];

        // Execute the Lua script
        $task = $this->redis->eval($this->luaScript, $args, 1);

        if ($task === false) {
            return null;
        }

        $queueJob = new QueueJob(json_decode((string) $task, true));

        // Set the actual status as in DB.
        $queueJob->status = Status::RESERVED->value;
        $queueJob->syncOriginal();

        $this->redis->hSet("queues:{$queue}::reserved", (string) $queueJob->id, json_encode($queueJob));

        return $queueJob;
    }

    /**
     * Schedule job for later
     *
     * @throws RedisException
     */
    public function later(QueueJob $queueJob, int $seconds): bool
    {
        $queueJob->status       = Status::PENDING->value;
        $queueJob->available_at = Time::now()->addSeconds($seconds);

        $result = (int) $this->redis->zAdd(
            "queues:{$queueJob->queue}:{$queueJob->priority}",
            $queueJob->available_at->timestamp,
            json_encode($queueJob),
        );
        if ($result !== 0) {
            $this->redis->hDel("queues:{$queueJob->queue}::reserved", (string) $queueJob->id);
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

        return (bool) $this->redis->hDel("queues:{$queueJob->queue}::reserved", (string) $queueJob->id);
    }

    /**
     * Change job status to DONE or delete it.
     *
     * @throws RedisException
     */
    public function done(QueueJob $queueJob): bool
    {
        return (bool) $this->redis->hDel("queues:{$queueJob->queue}::reserved", (string) $queueJob->id);
    }

    /**
     * Delete queue jobs
     *
     * @throws RedisException
     */
    public function clear(?string $queue = null): bool
    {
        if ($queue !== null) {
            $result = ($keys = $this->redis->keys("queues:{$queue}:*")) ? (int) $this->redis->del($keys) > 0 : true;
        } elseif ($keys = $this->redis->keys('queues:*')) {
            $result = (int) $this->redis->del($keys) > 0;
        } else {
            $result = true;
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
