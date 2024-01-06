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
use Exception;
use Predis\Client;
use Throwable;

class PredisHandler extends BaseHandler implements QueueInterface
{
    private readonly Client $predis;

    public function __construct(protected QueueConfig $config)
    {
        try {
            $this->predis = new Client($config->predis, ['prefix' => $config->predis['prefix']]);
            $this->predis->time();
        } catch (Exception $e) {
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

        $result = $this->predis->zadd("queues:{$queue}:{$this->priority}", [json_encode($queueJob) => Time::now()->timestamp]);

        $this->priority = null;

        return $result > 0;
    }

    /**
     * Get job from the queue.
     */
    public function pop(string $queue, array $priorities): ?QueueJob
    {
        $tasks = [];
        $now   = Time::now()->timestamp;

        foreach ($priorities as $priority) {
            $tasks = $this->predis->zrangebyscore(
                "queues:{$queue}:{$priority}",
                '-inf',
                $now,
                ['LIMIT' => [0, 1]]
            );
            if ($tasks !== []) {
                $removed = $this->predis->zrem("queues:{$queue}:{$priority}", ...$tasks);
                if ($removed !== 0) {
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
            [json_encode($queueJob) => $queueJob->available_at->timestamp]
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
    public function done(QueueJob $queueJob, bool $keepJob): bool
    {
        if ($keepJob) {
            $queueJob->status = Status::DONE->value;
            $this->predis->lpush("queues:{$queueJob->queue}::done", [json_encode($queueJob)]);
        }

        return (bool) $this->predis->hdel("queues:{$queueJob->queue}::reserved", [$queueJob->id]);
    }

    /**
     * Delete queue jobs
     */
    public function clear(?string $queue = null): bool
    {
        if ($queue !== null) {
            $keys = $this->predis->keys("queues:{$queue}:*");
            if ($keys !== []) {
                return $this->predis->del($keys) > 0;
            }

            return true;
        }

        $keys = $this->predis->keys('queues:*');
        if ($keys !== []) {
            return $this->predis->del($keys) > 0;
        }

        return true;
    }
}
