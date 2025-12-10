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
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\Models\QueueJobModel;
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use CodeIgniter\Queue\QueuePushResult;
use ReflectionException;
use RuntimeException;
use Throwable;

class DatabaseHandler extends BaseHandler implements QueueInterface
{
    private readonly QueueJobModel $jobModel;

    public function __construct(protected QueueConfig $config)
    {
        try {
            $connection     = db_connect($config->database['dbGroup'], $config->database['getShared']);
            $this->jobModel = model(QueueJobModel::class, true, $connection);

            // Emit connection established event
            QueueEventManager::handlerConnectionEstablished(
                handler: $this->name(),
                config: $config->database,
            );
        } catch (Throwable $e) {
            // Emit connection failed event
            QueueEventManager::handlerConnectionFailed(
                handler: $this->name(),
                exception: $e,
                config: $config->database,
            );

            throw new CriticalError('Queue: Database connection failed. ' . $e->getMessage());
        }
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
     */
    public function push(string $queue, string $job, array $data, ?PayloadMetadata $metadata = null): QueuePushResult
    {
        $this->validateJobAndPriority($queue, $job);

        $queueJob = new QueueJob([
            'queue'        => $queue,
            'payload'      => new Payload($job, $data, $metadata),
            'priority'     => $this->priority,
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => Time::now()->addSeconds($this->delay ?? 0),
        ]);

        $this->priority = $this->delay = null;

        try {
            $jobId = $this->jobModel->insert($queueJob);
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

        if ($jobId === 0) {
            $err = new RuntimeException('Failed to insert job into the database.');
            QueueEventManager::jobPushFailed(
                handler: $this->name(),
                queue: $queue,
                jobClass: $job,
                exception: $err,
            );

            return QueuePushResult::failure($err->getMessage());
        }

        // Set the job ID for the successful push event
        $queueJob->id = $jobId;

        // Emit job pushed event
        QueueEventManager::jobPushed(
            handler: $this->name(),
            queue: $queue,
            job: $queueJob,
        );

        return QueuePushResult::success($jobId);
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
     * Change job status to DONE or delete it.
     */
    public function done(QueueJob $queueJob): bool
    {
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

        $result = $this->jobModel->delete();

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
