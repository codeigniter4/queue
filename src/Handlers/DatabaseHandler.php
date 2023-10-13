<?php

namespace Michalsn\CodeIgniterQueue\Handlers;

use CodeIgniter\I18n\Time;
use Michalsn\CodeIgniterQueue\Entities\QueueJob;
use Michalsn\CodeIgniterQueue\Entities\QueueJobFailed;
use Michalsn\CodeIgniterQueue\Enums\Status;
use Michalsn\CodeIgniterQueue\Exceptions\QueueException;
use Michalsn\CodeIgniterQueue\Models\QueueJobFailedModel;
use Michalsn\CodeIgniterQueue\Payload;
use Michalsn\CodeIgniterQueue\Interfaces\QueueInterface;
use Michalsn\CodeIgniterQueue\Models\QueueJobModel;
use Michalsn\CodeIgniterQueue\Config\Queue as QueueConfig;
use InvalidArgumentException;
use ReflectionException;
use Throwable;

class DatabaseHandler implements QueueInterface
{
    private QueueJobModel $jobModel;

    public function __construct(protected QueueConfig $config)
    {
        $connection     = db_connect($config->database['dbGroup'], $config->database['getShared']);
        $this->jobModel = model(QueueJobModel::class, true, $connection);
        $this->jobModel->setTable($config->database['table']);
    }

    /**
     * Add job to the queue.
     *
     * @throws ReflectionException
     */
    public function push(string $queue, string $job, array $data): bool
    {
        if (! in_array($job, array_keys($this->config->jobHandlers), true)) {
            throw QueueException::forIncorrectJobHandler();
        }

        $queueJob = new QueueJob([
            'queue'        => $queue,
            'payload'      => new Payload($job, $data),
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => Time::now()->timestamp,
        ]);

        return $this->jobModel->insert($queueJob, false);
    }

    /**
     * Get job from the queue.
     *
     * @throws ReflectionException
     */
    public function pop(string $queue): ?QueueJob
    {
        return $this->jobModel->getFromQueue($queue);
    }

    /**
     * Schedule job for later
     *
     * @throws ReflectionException
     */
    public function later(QueueJob $queueJob, int $seconds): bool
    {
        $queueJob->status       = Status::PENDING->value;
        $queueJob->available_at = Time::now()->addSeconds($seconds)->timestamp;

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
            $exception = "Exception: {$err->getCode()} - {$err->getMessage()}" . PHP_EOL .
                "file: {$err->getFile()}:{$err->getLine()}";

            $queueJobFailed = new QueueJobFailed([
                'connection' => 'database',
                'queue'      => $queueJob->queue,
                'payload'    => $queueJob->payload,
                'exception'  => $exception,
            ]);
            model(QueueJobFailedModel::class)->insert($queueJobFailed, false);
        }

        return $this->jobModel->delete($queueJob->id);
    }

    /**
     * Change job status to DONE od delete it.
     */
    public function done(QueueJob $queueJob, bool $keepJob): bool
    {
        if ($keepJob) {
            return $this->jobModel->update($queueJob->id, ['status' => Status::DONE]);
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

    /**
     * Retry failed job.
     */
    public function retry(?int $id, ?string $queue): int
    {
        $jobs = model(QueueJobFailedModel::class)
            ->when(
                $id !== null,
                static fn ($query) => $query->where('id', $id)
            )
            ->when(
                $queue !== null,
                static fn ($query) => $query->where('queue', $queue)
            )
            ->findAll();

        foreach ($jobs as $job) {
            $this->push($job->queue, $job->payload['job'], $job->payload['data']);
            $this->forget($job->id);
        }

        return count($jobs);
    }

    /**
     * Delete failed job by ID.
     */
    public function forget(int $id, $affectedRows = false): bool
    {
        return model(QueueJobFailedModel::class)->delete($id) &&
            (! $affectedRows || model(QueueJobFailedModel::class)->affectedRows() > 0);
    }

    /**
     * Delete many failed jobs at once.
     */
    public function flush(?int $hours, ?string $queue): bool
    {
        return model(QueueJobFailedModel::class)
            ->when(
                $hours !== null,
                static fn ($query) => $query->where('failed_at <=', Time::now()->subHours($hours)->timestamp)
            )
            ->when(
                $queue !== null,
                static fn ($query) => $query->where('queue', $queue)
            )
            ->delete();
    }

    /**
     * List failed queue jobs.
     */
    public function listFailed(?string $queue)
    {
        return model(QueueJobFailedModel::class)
            ->when(
                $queue !== null,
                static fn ($query) => $query->where('queue', $queue)
            )
            ->orderBy('failed_at', 'desc')
            ->findAll();
    }
}
