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

namespace Tests;

use CodeIgniter\Exceptions\CriticalError;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Handlers\RabbitMQHandler;
use CodeIgniter\Queue\QueuePushResult;
use CodeIgniter\Test\ReflectionHelper;
use Exception;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\TestCase;
use Throwable;

/**
 * @internal
 */
final class RabbitMQHandlerTest extends TestCase
{
    use ReflectionHelper;

    private QueueConfig $config;
    private ?RabbitMQHandler $handler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);

        // Skip tests if RabbitMQ is not available
        if (! $this->isRabbitMQAvailable()) {
            $this->markTestSkipped('RabbitMQ is not available for testing');
        }

        try {
            $this->handler = new RabbitMQHandler($this->config);
        } catch (CriticalError) {
            $this->markTestSkipped('Cannot connect to RabbitMQ server');
        }
    }

    protected function tearDown(): void
    {
        if ($this->handler !== null) {
            // Clear test queues
            try {
                $this->handler->clear('test-queue');
                $this->handler->clear('test-queue-1');
                $this->handler->clear('test-queue-2');
                $this->handler->clear('priority-test');
                $this->handler->clear('custom-priority-queue');
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    public function testRabbitMQHandler(): void
    {
        $this->assertInstanceOf(RabbitMQHandler::class, $this->handler);
        $this->assertSame('rabbitmq', $this->handler->name());
    }

    public function testRabbitMQConnectionFailure(): void
    {
        $this->expectException(CriticalError::class);
        $this->expectExceptionMessage('Queue: RabbitMQ connection failed.');

        $badConfig                   = clone $this->config;
        $badConfig->rabbitmq['host'] = 'nonexistent-host';
        $badConfig->rabbitmq['port'] = 12345;

        new RabbitMQHandler($badConfig);
    }

    public function testPushJob(): void
    {
        $result = $this->handler->push('test-queue', 'success', ['message' => 'Hello World']);

        $this->assertInstanceOf(QueuePushResult::class, $result);
        $this->assertTrue($result->getStatus());
        $this->assertIsInt($result->getJobId());
        $this->assertNull($result->getError());
    }

    public function testPushJobWithDelay(): void
    {
        $result = $this->handler->setDelay(30)->push('test-queue', 'success', ['message' => 'Delayed']);

        $this->assertInstanceOf(QueuePushResult::class, $result);
        $this->assertTrue($result->getStatus());
    }

    public function testPushJobWithPriority(): void
    {
        $this->config->queuePriorities['priority-test'] = ['high', 'default', 'low'];

        $result = $this->handler->setPriority('high')->push('priority-test', 'success', ['priority' => 'high']);

        $this->assertTrue($result->getStatus());
    }

    public function testPopJob(): void
    {
        $this->handler->push('test-queue', 'success', ['message' => 'Test Pop']);

        // Give RabbitMQ a moment to process
        usleep(100_000);

        $job = $this->handler->pop('test-queue', ['default']);

        if ($job !== null) {
            $this->assertInstanceOf(QueueJob::class, $job);
            $this->assertSame('test-queue', $job->queue);
            $this->assertSame('success', $job->payload['job']);
            $this->assertSame(['message' => 'Test Pop'], $job->payload['data']);

            // Clean up - mark as done
            $this->handler->done($job);
        }
    }

    public function testPopJobWithPriorities(): void
    {
        $this->config->queuePriorities['priority-test'] = ['high', 'default', 'low'];

        // Push jobs with different priorities
        $this->handler->setPriority('low')->push('priority-test', 'success', ['priority' => 'low']);
        $this->handler->setPriority('high')->push('priority-test', 'success', ['priority' => 'high']);
        $this->handler->setPriority('default')->push('priority-test', 'success', ['priority' => 'default']);

        usleep(100_000);

        // Should get high priority job first
        $job = $this->handler->pop('priority-test', ['high', 'default', 'low']);

        if ($job !== null) {
            $this->assertSame('high', $job->priority);
            $this->handler->done($job);
        }
    }

    public function testJobFailure(): void
    {
        $this->handler->push('test-queue', 'failure', ['message' => 'Will Fail']);

        usleep(100_000);

        $job = $this->handler->pop('test-queue', ['default']);

        if ($job !== null) {
            $exception = new Exception('Test failure');
            $result    = $this->handler->failed($job, $exception, false);

            $this->assertTrue($result);
        }
    }

    public function testJobLater(): void
    {
        $this->handler->push('test-queue', 'success', ['message' => 'Reschedule']);

        usleep(100_000);

        $job = $this->handler->pop('test-queue', ['default']);

        if ($job !== null) {
            $result = $this->handler->later($job, 60);
            $this->assertTrue($result);
        }
    }

    public function testClearQueue(): void
    {
        $this->handler->push('test-queue', 'success', ['message' => 'Clear Test 1']);
        $this->handler->push('test-queue', 'success', ['message' => 'Clear Test 2']);

        usleep(100_000);

        $result = $this->handler->clear('test-queue');
        $this->assertTrue($result);

        // Verify queue is empty
        $job = $this->handler->pop('test-queue', ['default']);
        $this->assertNull($job);
    }

    public function testIncorrectJobHandler(): void
    {
        $this->expectException(QueueException::class);

        $this->handler->push('test-queue', 'nonexistent-job', []);
    }

    public function testIncorrectQueueFormat(): void
    {
        $this->expectException(QueueException::class);

        $this->handler->push('invalid queue name!', 'success', []);
    }

    public function testIncorrectPriority(): void
    {
        $this->expectException(QueueException::class);

        $this->config->queuePriorities['test-queue'] = ['high', 'low'];

        $this->handler->setPriority('medium')->push('test-queue', 'success', []);
    }

    public function testCustomPriorityMapping(): void
    {
        // Define custom priorities for a queue
        $this->config->queuePriorities['custom-priority-queue'] = ['urgent', 'normal', 'low'];

        // Test that we can push jobs with custom priorities
        $result1 = $this->handler->setPriority('urgent')->push('custom-priority-queue', 'success', ['priority' => 'urgent']);
        $result2 = $this->handler->setPriority('normal')->push('custom-priority-queue', 'success', ['priority' => 'normal']);
        $result3 = $this->handler->setPriority('low')->push('custom-priority-queue', 'success', ['priority' => 'low']);

        $this->assertTrue($result1->getStatus());
        $this->assertTrue($result2->getStatus());
        $this->assertTrue($result3->getStatus());

        usleep(100_000);

        // Should get urgent priority job first
        $job = $this->handler->pop('custom-priority-queue', ['urgent', 'normal', 'low']);
        if ($job !== null) {
            $this->assertSame('urgent', $job->payload['data']['priority']);
            $this->handler->done($job);
        }

        // Then normal priority
        $job = $this->handler->pop('custom-priority-queue', ['urgent', 'normal', 'low']);
        if ($job !== null) {
            $this->assertSame('normal', $job->payload['data']['priority']);
            $this->handler->done($job);
        }

        // Finally low priority
        $job = $this->handler->pop('custom-priority-queue', ['urgent', 'normal', 'low']);
        if ($job !== null) {
            $this->assertSame('low', $job->payload['data']['priority']);
            $this->handler->done($job);
        }
    }

    public function testPriority(): void
    {
        $this->handler->setPriority('high');

        $this->assertSame('high', self::getPrivateProperty($this->handler, 'priority'));
    }

    public function testPriorityException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name should consists only lowercase letters.');

        $this->handler->setPriority('high_:');
    }

    public function testPopEmpty(): void
    {
        $result = $this->handler->pop('empty-queue', ['default']);

        $this->assertNull($result);
    }

    public function testFailedAndKeepJob(): void
    {
        $this->handler->push('test-queue', 'success', ['test' => 'data']);
        $queueJob = $this->handler->pop('test-queue', ['default']);

        $this->assertInstanceOf(QueueJob::class, $queueJob);

        $err    = new Exception('Sample exception');
        $result = $this->handler->failed($queueJob, $err, true);

        $this->assertTrue($result);

        $this->seeInDatabase('queue_jobs_failed', [
            'queue'      => 'test-queue',
            'connection' => 'rabbitmq',
        ]);
    }

    public function testFailedAndDontKeepJob(): void
    {
        $this->handler->push('test-queue', 'success', ['test' => 'data']);
        $queueJob = $this->handler->pop('test-queue', ['default']);

        $this->assertInstanceOf(QueueJob::class, $queueJob);

        $err    = new Exception('Sample exception');
        $result = $this->handler->failed($queueJob, $err, false);

        $this->assertTrue($result);

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'queue'      => 'test-queue',
            'connection' => 'rabbitmq',
        ]);
    }

    public function testDone(): void
    {
        $this->handler->push('test-queue', 'success', ['test' => 'data']);
        $queueJob = $this->handler->pop('test-queue', ['default']);

        $this->assertInstanceOf(QueueJob::class, $queueJob);

        $result = $this->handler->done($queueJob);

        // Job is acknowledged and removed from RabbitMQ
        $this->assertTrue($result);
    }

    public function testClearAll(): void
    {
        $this->handler->push('test-queue-1', 'success', ['test' => 'data1']);
        $this->handler->push('test-queue-2', 'success', ['test' => 'data2']);

        usleep(100_000);

        $job1 = $this->handler->pop('test-queue-1', ['default']);
        $job2 = $this->handler->pop('test-queue-2', ['default']);

        $this->assertInstanceOf(QueueJob::class, $job1);
        $this->assertInstanceOf(QueueJob::class, $job2);

        // Put jobs back by rejecting them
        if (isset($job1->amqpDeliveryTag)) {
            $channel = self::getPrivateProperty($this->handler, 'channel');
            $channel->basic_nack($job1->amqpDeliveryTag, false, true); // requeue=true
        }
        if (isset($job2->amqpDeliveryTag)) {
            $channel = self::getPrivateProperty($this->handler, 'channel');
            $channel->basic_nack($job2->amqpDeliveryTag, false, true);
        }

        usleep(100_000);

        // Clear all queues
        $result = $this->handler->clear();
        $this->assertTrue($result);

        // Verify queues are empty by attempting to pop
        $jobAfter1 = $this->handler->pop('test-queue-1', ['default']);
        $jobAfter2 = $this->handler->pop('test-queue-2', ['default']);

        $this->assertNull($jobAfter1);
        $this->assertNull($jobAfter2);
    }

    public function testJsonEncodeExceptionMethod(): void
    {
        $exception = QueueException::forFailedJsonEncode('Malformed UTF-8 characters');

        $this->assertInstanceOf(QueueException::class, $exception);
        $this->assertStringContainsString('Failed to JSON encode queue job: Malformed UTF-8 characters', $exception->getMessage());
    }

    /**
     * Check if RabbitMQ is available for testing.
     */
    private function isRabbitMQAvailable(): bool
    {
        return class_exists(AMQPConnectionFactory::class);
    }
}
