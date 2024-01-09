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

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\Models\QueueJobModel;
use CodeIgniter\Queue\Payload;
use ReflectionException;
use Throwable;

class DatabaseHandler extends BaseHandler implements QueueInterface
{
    private readonly QueueJobModel $jobModel;

    public function __construct(protected QueueConfig $config)
    {
        $connection     = db_connect($config->database['dbGroup'], $config->database['getShared']);
        $this->jobModel = model(QueueJobModel::class, true, $connection);
    }

    /**
     * Name of the handler.
     */
    public function name(): string
    {
        return 'database';
    }

    /**
     * Add job to the queue.
     *
     * @throws ReflectionException
     */
    public function push(string $queue, string $job, array $data): bool
    {
        $this->validateJobAndPriority($queue, $job);

        $queueJob = new QueueJob([
            'queue'        => $queue,
            'payload'      => new Payload($job, $data),
            'priority'     => $this->priority,
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => Time::now(),
        ]);

        $this->priority = null;

        return $this->jobModel->insert($queueJob, false);
    }

    /**
     * Get job from the queue.
     *
     * @throws ReflectionException
     */
    public function pop(string $queue, array $priorities): ?QueueJob
    {
        $queueJob = $this->jobModel->getFromQueue($queue, $priorities);

        if ($queueJob === null) {
            return null;
        }

        // Set the actual status as in DB.
        $queueJob->status = Status::RESERVED->value;
        $queueJob->syncOriginal();

        return $queueJob;
    }

    /**
     * Schedule job for later
     *
     * @throws ReflectionException
     */
    public function later(QueueJob $queueJob, int $seconds): bool
    {
        $queueJob->status       = Status::PENDING->value;
        $queueJob->available_at = Time::now()->addSeconds($seconds);

        return $this->jobModel->save($queueJob);
    }

    /**
     * Move job to failed table or move and delete.
     *
     * @throws ReflectionException
     */
    public function failed(QueueJob $queueJob, Throwable $err, bool $keepJob): bool
    {
        if ($keepJob) {
            $this->logFailed($queueJob, $err);
        }

        return $this->jobModel->delete($queueJob->id);
    }

    /**
     * Change job status to DONE od delete it.
     *
     * @throws ReflectionException
     */
    public function done(QueueJob $queueJob, bool $keepJob): bool
    {
        if ($keepJob) {
            return $this->jobModel->update($queueJob->id, ['status' => Status::DONE->value]);
        }

        return $this->jobModel->delete($queueJob->id);
    }

    /**
     * Delete queue jobs
     */
    public function clear(?string $queue = null): bool
    {
        if ($queue !== null) {
            $this->jobModel->where('queue', $queue);
        }

        return $this->jobModel->delete();
    }
}
