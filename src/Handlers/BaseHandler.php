<?php

namespace Michalsn\CodeIgniterQueue\Handlers;

use CodeIgniter\I18n\Time;
use Michalsn\CodeIgniterQueue\Entities\QueueJob;
use Michalsn\CodeIgniterQueue\Entities\QueueJobFailed;
use Michalsn\CodeIgniterQueue\Exceptions\QueueException;
use Michalsn\CodeIgniterQueue\Models\QueueJobFailedModel;
use ReflectionException;
use Throwable;

abstract class BaseHandler
{
    protected ?string $priority = null;

    /**
     * Set priority for job queue.
     */
    public function setPriority(string $priority): static
    {
        if (! preg_match('/^[a-z_-]+$/', $priority)) {
            throw QueueException::forIncorrectPriorityFormat();
        }

        if (strlen($priority) > 64) {
            throw QueueException::forTooLongPriorityName();
        }

        $this->priority = $priority;

        return $this;
    }

    /**
     * Retry failed job.
     *
     * @throws ReflectionException
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
            $this->setPriority($job->priority)->push($job->queue, $job->payload['job'], $job->payload['data']);
            $this->forget($job->id);
        }

        return count($jobs);
    }

    /**
     * Delete failed job by ID.
     */
    public function forget(int $id): bool
    {
        if (model(QueueJobFailedModel::class)->delete($id)) {
            return model(QueueJobFailedModel::class)->affectedRows() > 0;
        }

        return false;
    }

    /**
     * Delete many failed jobs at once.
     */
    public function flush(?int $hours, ?string $queue): bool
    {
        if ($hours === null && $queue === null) {
            return model(QueueJobFailedModel::class)->truncate();
        }

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
    public function listFailed(?string $queue): array
    {
        return model(QueueJobFailedModel::class)
            ->when(
                $queue !== null,
                static fn ($query) => $query->where('queue', $queue)
            )
            ->orderBy('failed_at', 'desc')
            ->findAll();
    }

    /**
     * Log failed job.
     *
     * @throws ReflectionException
     */
    protected function logFailed(QueueJob $queueJob, Throwable $err): bool
    {
        $exception = "Exception: {$err->getCode()} - {$err->getMessage()}" . PHP_EOL .
            "file: {$err->getFile()}:{$err->getLine()}";

        $queueJobFailed = new QueueJobFailed([
            'connection' => 'database',
            'queue'      => $queueJob->queue,
            'payload'    => $queueJob->payload,
            'priority'   => $queueJob->priority,
            'exception'  => $exception,
        ]);

        return model(QueueJobFailedModel::class)->insert($queueJobFailed, false);
    }

    /**
     * Validate job and priority.
     */
    protected function validateJobAndPriority(string $queue, string $job): void
    {
        // Validate jobHandler.
        if (! in_array($job, array_keys($this->config->jobHandlers), true)) {
            throw QueueException::forIncorrectJobHandler();
        }

        if ($this->priority === null) {
            $this->setPriority($this->config->queueDefaultPriority[$queue] ?? 'default');
        }

        // Validate non-standard priority.
        if (! in_array($this->priority, $this->config->queuePriorities[$queue] ?? ['default'], true)) {
            throw QueueException::forIncorrectQueuePriority($this->priority, $queue);
        }
    }
}
