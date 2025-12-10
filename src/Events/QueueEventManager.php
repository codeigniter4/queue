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

use CodeIgniter\Events\Events;
use CodeIgniter\Queue\Entities\QueueJob;
use Throwable;

class QueueEventManager
{
    // Event names for queue operations
    public const JOB_PUSHED                     = 'queue.job.pushed';
    public const JOB_PUSH_FAILED                = 'queue.job.push.failed';
    public const JOB_PROCESSING_STARTED         = 'queue.job.processing.started';
    public const JOB_PROCESSING_COMPLETED       = 'queue.job.processing.completed';
    public const JOB_FAILED                     = 'queue.job.failed';
    public const QUEUE_CLEARED                  = 'queue.cleared';
    public const WORKER_STARTED                 = 'queue.worker.started';
    public const WORKER_STOPPED                 = 'queue.worker.stopped';
    public const HANDLER_CONNECTION_FAILED      = 'queue.handler.connection.failed';
    public const HANDLER_CONNECTION_ESTABLISHED = 'queue.handler.connection.established';

    /**
     * Emit job pushed event
     */
    public static function jobPushed(
        string $handler,
        string $queue,
        QueueJob $job,
        array $metadata = [],
    ): void {
        $event = new QueueEvent(
            type: self::JOB_PUSHED,
            handler: $handler,
            queue: $queue,
            metadata: array_merge([
                'job_class' => $job->payload['job'],
                'job'       => $job,
            ], $metadata),
        );

        Events::trigger(self::JOB_PUSHED, $event);
    }

    /**
     * Emit job push failed event
     */
    public static function jobPushFailed(
        string $handler,
        string $queue,
        string $jobClass,
        Throwable $exception,
        array $metadata = [],
    ): void {
        $event = new QueueEvent(
            type: self::JOB_PUSH_FAILED,
            handler: $handler,
            queue: $queue,
            metadata: array_merge([
                'job_class' => $jobClass,
                'exception' => $exception,
            ], $metadata),
        );

        Events::trigger(self::JOB_PUSH_FAILED, $event);
    }

    /**
     * Emit job processing started event
     */
    public static function jobProcessingStarted(
        string $handler,
        string $queue,
        QueueJob $job,
        array $metadata = [],
    ): void {
        $event = new QueueEvent(
            type: self::JOB_PROCESSING_STARTED,
            handler: $handler,
            queue: $queue,
            metadata: array_merge([
                'job_class' => $job->payload['job'],
                'job'       => $job,
            ], $metadata),
        );

        Events::trigger(self::JOB_PROCESSING_STARTED, $event);
    }

    /**
     * Emit job processing completed event
     */
    public static function jobProcessingCompleted(
        string $handler,
        string $queue,
        QueueJob $job,
        float $processingTime,
        array $metadata = [],
    ): void {
        $event = new QueueEvent(
            type: self::JOB_PROCESSING_COMPLETED,
            handler: $handler,
            queue: $queue,
            metadata: array_merge([
                'job_class'       => $job->payload['job'],
                'job'             => $job,
                'processing_time' => $processingTime,
            ], $metadata),
        );

        Events::trigger(self::JOB_PROCESSING_COMPLETED, $event);
    }

    /**
     * Emit job failed event
     */
    public static function jobFailed(
        string $handler,
        string $queue,
        QueueJob $job,
        Throwable $exception,
        float $processingTime = 0.0,
        array $metadata = [],
    ): void {
        $event = new QueueEvent(
            type: self::JOB_FAILED,
            handler: $handler,
            queue: $queue,
            metadata: array_merge([
                'job_class'       => $job->payload['job'],
                'job'             => $job,
                'exception'       => $exception,
                'processing_time' => $processingTime,
            ], $metadata),
        );

        Events::trigger(self::JOB_FAILED, $event);
    }

    /**
     * Emit queue cleared event
     */
    public static function queueCleared(
        string $handler,
        ?string $queue = null,
    ): void {
        $event = new QueueEvent(
            type: self::QUEUE_CLEARED,
            handler: $handler,
            queue: $queue,
        );

        Events::trigger(self::QUEUE_CLEARED, $event);
    }

    /**
     * Emit worker started event
     */
    public static function workerStarted(
        string $handler,
        string $queue,
        array $priorities,
        array $config = [],
        array $metadata = [],
    ): void {
        $event = new QueueEvent(
            type: self::WORKER_STARTED,
            handler: $handler,
            queue: $queue,
            metadata: array_merge([
                'priorities' => $priorities,
                'config'     => $config,
            ], $metadata),
        );

        Events::trigger(self::WORKER_STARTED, $event);
    }

    /**
     * Emit worker stopped event
     */
    public static function workerStopped(
        string $handler,
        string $queue,
        array $priorities,
        float $uptime,
        int $jobsProcessed,
        array $metadata = [],
    ): void {
        $event = new QueueEvent(
            type: self::WORKER_STOPPED,
            handler: $handler,
            queue: $queue,
            metadata: array_merge([
                'priorities'     => $priorities,
                'uptime_seconds' => $uptime,
                'jobs_processed' => $jobsProcessed,
            ], $metadata),
        );

        Events::trigger(self::WORKER_STOPPED, $event);
    }

    /**
     * Emit handler connection established event
     */
    public static function handlerConnectionEstablished(
        string $handler,
        array $config = [],
    ): void {
        $event = new QueueEvent(
            type: self::HANDLER_CONNECTION_ESTABLISHED,
            handler: $handler,
            metadata: ['config' => $config],
        );

        Events::trigger(self::HANDLER_CONNECTION_ESTABLISHED, $event);
    }

    /**
     * Emit handler connection failed event
     */
    public static function handlerConnectionFailed(
        string $handler,
        Throwable $exception,
        array $config = [],
    ): void {
        $event = new QueueEvent(
            type: self::HANDLER_CONNECTION_FAILED,
            handler: $handler,
            metadata: [
                'exception' => $exception,
                'config'    => $config,
            ],
        );

        Events::trigger(self::HANDLER_CONNECTION_FAILED, $event);
    }
}
