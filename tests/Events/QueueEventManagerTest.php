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

use CodeIgniter\Events\Events;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Events\QueueEvent;
use CodeIgniter\Queue\Events\QueueEventManager;
use CodeIgniter\Queue\Payloads\Payload;
use Exception;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class QueueEventManagerTest extends TestCase
{
    private array $capturedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Clear captured events
        $this->capturedEvents = [];

        // Set up event listener to capture all queue events
        $this->setupEventCapture();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clear all event listeners
        Events::removeAllListeners();
    }

    public function testJobPushedEvent(): void
    {
        $queueJob = $this->createTestQueueJob();

        QueueEventManager::jobPushed('database', 'emails', $queueJob, ['extra' => 'data']);

        $this->assertEventWasTriggered(QueueEventManager::JOB_PUSHED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.job.pushed', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
        $this->assertSame('App\\Jobs\\TestJob', $event->getJobClass());
        $this->assertSame($queueJob, $event->getMetadata('job'));
        $this->assertSame('data', $event->getMetadata('extra'));
    }

    public function testJobPushFailedEvent(): void
    {
        $exception = new Exception('Push failed');

        QueueEventManager::jobPushFailed('redis', 'notifications', 'App\\Jobs\\EmailJob', $exception);

        $this->assertEventWasTriggered(QueueEventManager::JOB_PUSH_FAILED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.job.push.failed', $event->getType());
        $this->assertSame('redis', $event->getHandler());
        $this->assertSame('notifications', $event->getQueue());
        $this->assertSame('App\\Jobs\\EmailJob', $event->getJobClass());
        $this->assertSame($exception, $event->getException());
    }

    public function testJobProcessingStartedEvent(): void
    {
        $queueJob = $this->createTestQueueJob();

        QueueEventManager::jobProcessingStarted('database', 'emails', $queueJob, ['worker_id' => 'worker-1']);

        $this->assertEventWasTriggered(QueueEventManager::JOB_PROCESSING_STARTED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.job.processing.started', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
        $this->assertSame('App\\Jobs\\TestJob', $event->getJobClass());
        $this->assertSame($queueJob, $event->getMetadata('job'));
        $this->assertSame('worker-1', $event->getMetadata('worker_id'));
    }

    public function testJobProcessingCompletedEvent(): void
    {
        $queueJob       = $this->createTestQueueJob();
        $processingTime = 1.5;

        QueueEventManager::jobProcessingCompleted('database', 'emails', $queueJob, $processingTime, ['worker_id' => 'worker-1']);

        $this->assertEventWasTriggered(QueueEventManager::JOB_PROCESSING_COMPLETED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.job.processing.completed', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
        $this->assertSame('App\\Jobs\\TestJob', $event->getJobClass());
        $this->assertSame($queueJob, $event->getMetadata('job'));
        $this->assertEqualsWithDelta(1.5, $event->getProcessingTime(), PHP_FLOAT_EPSILON);
        $this->assertSame('worker-1', $event->getMetadata('worker_id'));
    }

    public function testJobFailedEvent(): void
    {
        $queueJob       = $this->createTestQueueJob();
        $exception      = new Exception('Job failed');
        $processingTime = 2.3;

        QueueEventManager::jobFailed('database', 'emails', $queueJob, $exception, $processingTime, ['worker_id' => 'worker-1']);

        $this->assertEventWasTriggered(QueueEventManager::JOB_FAILED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.job.failed', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
        $this->assertSame('App\\Jobs\\TestJob', $event->getJobClass());
        $this->assertSame($queueJob, $event->getMetadata('job'));
        $this->assertSame($exception, $event->getException());
        $this->assertEqualsWithDelta(2.3, $event->getProcessingTime(), PHP_FLOAT_EPSILON);
        $this->assertSame('worker-1', $event->getMetadata('worker_id'));
    }

    public function testQueueClearedEventWithSpecificQueue(): void
    {
        QueueEventManager::queueCleared('database', 'emails');

        $this->assertEventWasTriggered(QueueEventManager::QUEUE_CLEARED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.cleared', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
    }

    public function testQueueClearedEventWithoutQueue(): void
    {
        QueueEventManager::queueCleared('database');

        $this->assertEventWasTriggered(QueueEventManager::QUEUE_CLEARED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.cleared', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertNull($event->getQueue());
    }

    public function testWorkerStartedEvent(): void
    {
        $priorities = ['high', 'default'];
        $config     = ['timeout' => 300];

        QueueEventManager::workerStarted('database', 'emails', $priorities, $config, ['worker_id' => 'worker-1']);

        $this->assertEventWasTriggered(QueueEventManager::WORKER_STARTED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.worker.started', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
        $this->assertSame($priorities, $event->getMetadata('priorities'));
        $this->assertSame($config, $event->getMetadata('config'));
        $this->assertSame('worker-1', $event->getMetadata('worker_id'));
    }

    public function testWorkerStoppedEvent(): void
    {
        $priorities    = ['high', 'default'];
        $uptime        = 3600.5;
        $jobsProcessed = 25;

        QueueEventManager::workerStopped('database', 'emails', $priorities, $uptime, $jobsProcessed, ['worker_id' => 'worker-1']);

        $this->assertEventWasTriggered(QueueEventManager::WORKER_STOPPED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.worker.stopped', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertSame('emails', $event->getQueue());
        $this->assertSame($priorities, $event->getMetadata('priorities'));
        $this->assertEqualsWithDelta(3600.5, $event->getMetadata('uptime_seconds'), PHP_FLOAT_EPSILON);
        $this->assertSame(25, $event->getMetadata('jobs_processed'));
        $this->assertSame('worker-1', $event->getMetadata('worker_id'));
    }

    public function testHandlerConnectionEstablishedEvent(): void
    {
        $config = ['host' => 'localhost', 'port' => 3306];

        QueueEventManager::handlerConnectionEstablished('database', $config);

        $this->assertEventWasTriggered(QueueEventManager::HANDLER_CONNECTION_ESTABLISHED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.handler.connection.established', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertNull($event->getQueue());
        $this->assertSame($config, $event->getMetadata('config'));
    }

    public function testHandlerConnectionFailedEvent(): void
    {
        $exception = new Exception('Connection failed');
        $config    = ['host' => 'localhost', 'port' => 3306];

        QueueEventManager::handlerConnectionFailed('database', $exception, $config);

        $this->assertEventWasTriggered(QueueEventManager::HANDLER_CONNECTION_FAILED);
        $event = $this->getLastCapturedEvent();

        $this->assertSame('queue.handler.connection.failed', $event->getType());
        $this->assertSame('database', $event->getHandler());
        $this->assertNull($event->getQueue());
        $this->assertSame($exception, $event->getException());
        $this->assertSame($config, $event->getMetadata('config'));
    }

    private function createTestQueueJob(): QueueJob
    {
        $payload = new Payload('App\\Jobs\\TestJob', ['data' => 'test']);

        return new QueueJob([
            'id'       => 123,
            'queue'    => 'test-queue',
            'payload'  => $payload,
            'priority' => 'default',
            'status'   => 0,
            'attempts' => 0,
        ]);
    }

    private function setupEventCapture(): void
    {
        $constants = [
            QueueEventManager::JOB_PUSHED,
            QueueEventManager::JOB_PUSH_FAILED,
            QueueEventManager::JOB_PROCESSING_STARTED,
            QueueEventManager::JOB_PROCESSING_COMPLETED,
            QueueEventManager::JOB_FAILED,
            QueueEventManager::QUEUE_CLEARED,
            QueueEventManager::WORKER_STARTED,
            QueueEventManager::WORKER_STOPPED,
            QueueEventManager::HANDLER_CONNECTION_ESTABLISHED,
            QueueEventManager::HANDLER_CONNECTION_FAILED,
        ];

        foreach ($constants as $eventType) {
            Events::on($eventType, function (QueueEvent $event): void {
                $this->capturedEvents[] = $event;
            });
        }
    }

    private function assertEventWasTriggered(string $eventType): void
    {
        $found = false;

        foreach ($this->capturedEvents as $event) {
            if ($event->getType() === $eventType) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Event '{$eventType}' was not triggered");
    }

    private function getLastCapturedEvent(): QueueEvent
    {
        $this->assertNotEmpty($this->capturedEvents, 'No events were captured');

        return end($this->capturedEvents);
    }
}
