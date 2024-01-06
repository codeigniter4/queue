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
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\Payload;
use Redis;
use RedisException;
use Throwable;

class RedisHandler extends BaseHandler implements QueueInterface
{
    private readonly Redis $redis;

    public function __construct(protected QueueConfig $config)
    {
        $this->redis = new Redis();

        try {
            if (! $this->redis->connect($config->redis['host'], ($config->redis['host'][0] === '/' ? 0 : $config->redis['port']), $config->redis['timeout'])) {
                throw new CriticalError('Queue: Redis connection failed. Check your configuration.');
            }

            if (isset($config->redis['password']) && ! $this->redis->auth($config->redis['password'])) {
                throw new CriticalError('Queue: Redis authentication failed.');
            }

            if (isset($config->redis['database']) && ! $this->redis->select($config->redis['database'])) {
                throw new CriticalError('Queue: Redis select database failed.');
            }

            if (isset($config->redis['prefix']) && ! $this->redis->setOption(Redis::OPT_PREFIX, $config->redis['prefix'])) {
                throw new CriticalError('Queue: Redis setting prefix failed.');
            }
        } catch (RedisException $e) {
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
    public function push(string $queue, string $job, array $data): bool
    {
        $this->validateJobAndPriority($queue, $job);

        helper('text');

        $queueJob = new QueueJob([
            'id'           => random_string('numeric', 16),
            'queue'        => $queue,
            'payload'      => new Payload($job, $data),
            'priority'     => $this->priority,
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => Time::now(),
        ]);

        $result = (int) $this->redis->zAdd("queues:{$queue}:{$this->priority}", Time::now()->timestamp, json_encode($queueJob));

        $this->priority = null;

        return $result > 0;
    }

    /**
     * Get job from the queue.
     *
     * @throws RedisException
     */
    public function pop(string $queue, array $priorities): ?QueueJob
    {
        $tasks = [];
        $now   = Time::now()->timestamp;

        foreach ($priorities as $priority) {
            if ($tasks = $this->redis->zRangeByScore("queues:{$queue}:{$priority}", '-inf', (string) $now, ['limit' => [0, 1]])) {
                if ($this->redis->zRem("queues:{$queue}:{$priority}", ...$tasks)) {
                    break;
                }
                $tasks = [];
            }
        }

        if ($tasks === []) {
            return null;
        }

        $queueJob = new QueueJob(json_decode((string) $tasks[0], true));

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
            json_encode($queueJob)
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
    public function done(QueueJob $queueJob, bool $keepJob): bool
    {
        if ($keepJob) {
            $queueJob->status = Status::DONE->value;
            $this->redis->lPush("queues:{$queueJob->queue}::done", json_encode($queueJob));
        }

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
            if ($keys = $this->redis->keys("queues:{$queue}:*")) {
                return (int) $this->redis->del($keys) > 0;
            }

            return true;
        }

        if ($keys = $this->redis->keys('queues:*')) {
            return (int) $this->redis->del($keys) > 0;
        }

        return true;
    }
}
