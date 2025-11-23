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

namespace CodeIgniter\Queue\Events;

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Entities\QueueJob;
use Throwable;

class QueueEvent
{
    private readonly Time $timestamp;

    public function __construct(
        private readonly string $type,
        private readonly string $handler,
        private readonly ?string $queue = null,
        private readonly array $metadata = [],
        ?Time $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? Time::now();
    }

    /**
     * Get event type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get handler name
     */
    public function getHandler(): string
    {
        return $this->handler;
    }

    /**
     * Get queue name
     */
    public function getQueue(): ?string
    {
        return $this->queue;
    }

    /**
     * Get timestamp
     */
    public function getTimestamp(): Time
    {
        return $this->timestamp;
    }

    /**
     * Get all metadata
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get metadata value by key
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if this is a job-related event
     */
    public function isJobEvent(): bool
    {
        return str_starts_with($this->type, 'queue.job.');
    }

    /**
     * Check if this is a worker-related event
     */
    public function isWorkerEvent(): bool
    {
        return str_starts_with($this->type, 'queue.worker.');
    }

    /**
     * Check if this is an operation event (like queue.cleared)
     */
    public function isOperationEvent(): bool
    {
        return str_contains($this->type, 'queue.')
               && ! $this->isJobEvent()
               && ! $this->isWorkerEvent()
               && ! $this->isConnectionEvent();
    }

    /**
     * Check if this is a connection event
     */
    public function isConnectionEvent(): bool
    {
        return str_starts_with($this->type, 'queue.handler.');
    }

    // Job-related convenience methods (metadata-based)

    /**
     * Get job ID (for job events)
     */
    public function getJobId(): ?int
    {
        $job = $this->getMetadata('job');

        return $job instanceof QueueJob ? $job->id : $this->getMetadata('job_id');
    }

    /**
     * Get job priority (for job events)
     */
    public function getPriority(): ?string
    {
        $job = $this->getMetadata('job');

        return $job instanceof QueueJob ? $job->priority : $this->getMetadata('priority');
    }

    /**
     * Get number of attempts (for job events)
     */
    public function getAttempts(): ?int
    {
        $job = $this->getMetadata('job');

        return $job instanceof QueueJob ? $job->attempts : $this->getMetadata('attempts');
    }

    /**
     * Get job status (for job events)
     */
    public function getStatus(): ?int
    {
        $job = $this->getMetadata('job');

        return $job instanceof QueueJob ? $job->status : $this->getMetadata('status');
    }

    /**
     * Get job class name (for job events)
     */
    public function getJobClass(): ?string
    {
        return $this->getMetadata('job_class');
    }

    /**
     * Get processing time in seconds (for job events)
     */
    public function getProcessingTime(): float
    {
        return (float) $this->getMetadata('processing_time', 0.0);
    }

    /**
     * Get processing time in milliseconds (for job events)
     */
    public function getProcessingTimeMs(): int
    {
        return (int) ($this->getProcessingTime() * 1000);
    }

    /**
     * Get exception (for failed events)
     */
    public function getException(): ?Throwable
    {
        return $this->getMetadata('exception');
    }

    /**
     * Get exception message (for failed events)
     */
    public function getExceptionMessage(): ?string
    {
        $exception = $this->getException();

        return $exception?->getMessage();
    }

    /**
     * Check if event has failed
     */
    public function hasFailed(): bool
    {
        return $this->getException() !== null;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'type'      => $this->type,
            'handler'   => $this->handler,
            'queue'     => $this->queue,
            'metadata'  => $this->metadata,
            'timestamp' => $this->timestamp->toDateTimeString(),
        ];
    }
}
