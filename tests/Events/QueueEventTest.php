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

namespace Tests\Events;

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Events\QueueEvent;
use Exception;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class QueueEventTest extends TestCase
{
    public function testConstructorWithMinimalParameters(): void
    {
        $event = new QueueEvent('queue.job.pushed', 'database');

        $this->assertSame('queue.job.pushed', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertNull($event->getQueue());
        $this->assertSame([], $event->getAllMetadata());
        $this->assertInstanceOf(Time::class, $event->getTimestamp());
    }

    public function testConstructorWithAllParameters(): void
    {
        $timestamp = Time::parse('2023-01-01 12:00:00');
        $metadata  = ['job_class' => 'TestJob', 'attempts' => 3];

        $event = new QueueEvent(
            type: 'queue.job.failed',
            handler: 'redis',
            queue: 'emails',
            metadata: $metadata,
            timestamp: $timestamp,
        );

        $this->assertSame('queue.job.failed', $event->getType());
        $this->assertSame('redis', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
        $this->assertSame($metadata, $event->getAllMetadata());
        $this->assertSame($timestamp, $event->getTimestamp());
    }

    public function testGetMetadataWithExistingKey(): void
    {
        $metadata = ['job_class' => 'TestJob', 'priority' => 'high'];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame('TestJob', $event->getMetadata('job_class'));
        $this->assertSame('high', $event->getMetadata('priority'));
    }

    public function testGetMetadataWithNonExistingKey(): void
    {
        $event = new QueueEvent('queue.job.pushed', 'database');

        $this->assertNull($event->getMetadata('non_existing'));
        $this->assertSame('default_value', $event->getMetadata('non_existing', 'default_value'));
    }

    public function testIsJobEvent(): void
    {
        $jobEvent       = new QueueEvent('queue.job.pushed', 'database');
        $workerEvent    = new QueueEvent('queue.worker.started', 'database');
        $operationEvent = new QueueEvent('queue.cleared', 'database');

        $this->assertTrue($jobEvent->isJobEvent());
        $this->assertFalse($workerEvent->isJobEvent());
        $this->assertFalse($operationEvent->isJobEvent());
    }

    public function testIsWorkerEvent(): void
    {
        $jobEvent       = new QueueEvent('queue.job.pushed', 'database');
        $workerEvent    = new QueueEvent('queue.worker.started', 'database');
        $operationEvent = new QueueEvent('queue.cleared', 'database');

        $this->assertFalse($jobEvent->isWorkerEvent());
        $this->assertTrue($workerEvent->isWorkerEvent());
        $this->assertFalse($operationEvent->isWorkerEvent());
    }

    public function testIsOperationEvent(): void
    {
        $jobEvent        = new QueueEvent('queue.job.pushed', 'database');
        $workerEvent     = new QueueEvent('queue.worker.started', 'database');
        $operationEvent  = new QueueEvent('queue.cleared', 'database');
        $connectionEvent = new QueueEvent('queue.handler.connection.failed', 'database');

        $this->assertFalse($jobEvent->isOperationEvent());
        $this->assertFalse($workerEvent->isOperationEvent());
        $this->assertTrue($operationEvent->isOperationEvent());
        $this->assertFalse($connectionEvent->isOperationEvent());
    }

    public function testIsConnectionEvent(): void
    {
        $jobEvent                   = new QueueEvent('queue.job.pushed', 'database');
        $connectionEvent            = new QueueEvent('queue.handler.connection.failed', 'database');
        $connectionEstablishedEvent = new QueueEvent('queue.handler.connection.established', 'database');

        $this->assertFalse($jobEvent->isConnectionEvent());
        $this->assertTrue($connectionEvent->isConnectionEvent());
        $this->assertTrue($connectionEstablishedEvent->isConnectionEvent());
    }

    public function testGetJobIdFromQueueJobObject(): void
    {
        $queueJob = new QueueJob(['id' => 123, 'queue' => 'test', 'payload' => ['job' => 'TestJob']]);
        $metadata = ['job' => $queueJob];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame(123, $event->getJobId());
    }

    public function testGetJobIdFromMetadata(): void
    {
        $metadata = ['job_id' => 456];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame(456, $event->getJobId());
    }

    public function testGetJobIdReturnsNull(): void
    {
        $event = new QueueEvent('queue.job.pushed', 'database');

        $this->assertNull($event->getJobId());
    }

    public function testGetPriorityFromQueueJobObject(): void
    {
        $queueJob = new QueueJob(['priority' => 'high', 'queue' => 'test', 'payload' => ['job' => 'TestJob']]);
        $metadata = ['job' => $queueJob];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame('high', $event->getPriority());
    }

    public function testGetPriorityFromMetadata(): void
    {
        $metadata = ['priority' => 'low'];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame('low', $event->getPriority());
    }

    public function testGetAttemptsFromQueueJobObject(): void
    {
        $queueJob = new QueueJob(['attempts' => 3, 'queue' => 'test', 'payload' => ['job' => 'TestJob']]);
        $metadata = ['job' => $queueJob];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame(3, $event->getAttempts());
    }

    public function testGetStatusFromQueueJobObject(): void
    {
        $queueJob = new QueueJob(['status' => 1, 'queue' => 'test', 'payload' => ['job' => 'TestJob']]);
        $metadata = ['job' => $queueJob];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame(1, $event->getStatus());
    }

    public function testGetJobClass(): void
    {
        $metadata = ['job_class' => 'App\\Jobs\\TestJob'];
        $event    = new QueueEvent('queue.job.pushed', 'database', metadata: $metadata);

        $this->assertSame('App\\Jobs\\TestJob', $event->getJobClass());
    }

    public function testGetProcessingTime(): void
    {
        $metadata = ['processing_time' => 1.5];
        $event    = new QueueEvent('queue.job.completed', 'database', metadata: $metadata);

        $this->assertEqualsWithDelta(1.5, $event->getProcessingTime(), PHP_FLOAT_EPSILON);
    }

    public function testGetProcessingTimeDefaultsToZero(): void
    {
        $event = new QueueEvent('queue.job.completed', 'database');

        $this->assertEqualsWithDelta(0.0, $event->getProcessingTime(), PHP_FLOAT_EPSILON);
    }

    public function testGetProcessingTimeMs(): void
    {
        $metadata = ['processing_time' => 1.5];
        $event    = new QueueEvent('queue.job.completed', 'database', metadata: $metadata);

        $this->assertSame(1500, $event->getProcessingTimeMs());
    }

    public function testGetExceptionAndExceptionMessage(): void
    {
        $exception = new Exception('Test error message');
        $metadata  = ['exception' => $exception];
        $event     = new QueueEvent('queue.job.failed', 'database', metadata: $metadata);

        $this->assertSame($exception, $event->getException());
        $this->assertSame('Test error message', $event->getExceptionMessage());
        $this->assertTrue($event->hasFailed());
    }

    public function testGetExceptionReturnsNullWhenNoException(): void
    {
        $event = new QueueEvent('queue.job.completed', 'database');

        $this->assertNull($event->getException());
        $this->assertNull($event->getExceptionMessage());
        $this->assertFalse($event->hasFailed());
    }

    public function testToArray(): void
    {
        $timestamp = Time::parse('2023-01-01 12:00:00');
        $metadata  = ['job_class' => 'TestJob', 'attempts' => 3];

        $event = new QueueEvent(
            type: 'queue.job.failed',
            handler: 'redis',
            queue: 'emails',
            metadata: $metadata,
            timestamp: $timestamp,
        );

        $expected = [
            'type'      => 'queue.job.failed',
            'handler'   => 'redis',
            'queue'     => 'emails',
            'metadata'  => $metadata,
            'timestamp' => $timestamp->toDateTimeString(),
        ];

        $this->assertSame($expected, $event->toArray());
    }

    public function testToArrayWithNullQueue(): void
    {
        $timestamp = Time::parse('2023-01-01 12:00:00');

        $event = new QueueEvent(
            type: 'queue.job.failed',
            handler: 'redis',
            timestamp: $timestamp,
        );

        $expected = [
            'type'      => 'queue.job.failed',
            'handler'   => 'redis',
            'queue'     => null,
            'metadata'  => [],
            'timestamp' => $timestamp->toDateTimeString(),
        ];

        $this->assertSame($expected, $event->toArray());
    }
}
