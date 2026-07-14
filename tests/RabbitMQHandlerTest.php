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

    private string $customPriorityQueue;
    private string $emptyQueue;
    private QueueConfig $config;
    private ?RabbitMQHandler $handler = null;
    private string $priorityQueue;
    private string $testQueue;
    private string $testQueue1;
    private string $testQueue2;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix                    = bin2hex(random_bytes(6));
        $this->testQueue           = 'test-queue-' . $suffix;
        $this->testQueue1          = 'test-queue-1-' . $suffix;
        $this->testQueue2          = 'test-queue-2-' . $suffix;
        $this->priorityQueue       = 'priority-test-' . $suffix;
        $this->customPriorityQueue = 'custom-priority-queue-' . $suffix;
        $this->emptyQueue          = 'empty-queue-' . $suffix;
        $this->config              = config(QueueConfig::class);

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
            try {
                $this->deleteDeclaredResources();
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
        $result = $this->handler->push($this->testQueue, 'success', ['message' => 'Hello World']);

        $this->assertInstanceOf(QueuePushResult::class, $result);
        $this->assertTrue($result->getStatus());
        $this->assertIsInt($result->getJobId());
        $this->assertNull($result->getError());
    }

    public function testPushJobWithDelay(): void
    {
        $result = $this->handler->setDelay(30)->push($this->testQueue, 'success', ['message' => 'Delayed']);

        $this->assertInstanceOf(QueuePushResult::class, $result);
        $this->assertTrue($result->getStatus());
    }

    public function testPushJobWithPriority(): void
    {
        $this->config->queuePriorities[$this->priorityQueue] = ['high', 'default', 'low'];

        $result = $this->handler->setPriority('high')->push($this->priorityQueue, 'success', ['priority' => 'high']);

        $this->assertTrue($result->getStatus());
    }

    public function testPopJob(): void
    {
        $this->handler->push($this->testQueue, 'success', ['message' => 'Test Pop']);

        $job = $this->popEventually($this->testQueue, ['default']);

        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame($this->testQueue, $job->queue);
        $this->assertSame('success', $job->payload['job']);
        $this->assertSame(['message' => 'Test Pop'], $job->payload['data']);

        // Clean up - mark as done
        $this->handler->done($job);
    }

    public function testPopJobWithPriorities(): void
    {
        $this->config->queuePriorities[$this->priorityQueue] = ['high', 'default', 'low'];

        // Push jobs with different priorities
        $this->handler->setPriority('low')->push($this->priorityQueue, 'success', ['priority' => 'low']);
        $this->handler->setPriority('high')->push($this->priorityQueue, 'success', ['priority' => 'high']);
        $this->handler->setPriority('default')->push($this->priorityQueue, 'success', ['priority' => 'default']);

        // Should get high priority job first
        $job = $this->popEventually($this->priorityQueue, ['high', 'default', 'low']);

        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('high', $job->priority);
        $this->handler->done($job);
    }

    public function testJobFailure(): void
    {
        $this->handler->push($this->testQueue, 'failure', ['message' => 'Will Fail']);

        $job = $this->popEventually($this->testQueue, ['default']);

        $this->assertInstanceOf(QueueJob::class, $job);
        $exception = new Exception('Test failure');
        $result    = $this->handler->failed($job, $exception, false);

        $this->assertTrue($result);
    }

    public function testJobLater(): void
    {
        $this->handler->push($this->testQueue, 'success', ['message' => 'Reschedule']);

        $job = $this->popEventually($this->testQueue, ['default']);

        $this->assertInstanceOf(QueueJob::class, $job);
        $result = $this->handler->later($job, 60);
        $this->assertTrue($result);
    }

    public function testClearQueue(): void
    {
        $this->handler->push($this->testQueue, 'success', ['message' => 'Clear Test 1']);
        $this->handler->push($this->testQueue, 'success', ['message' => 'Clear Test 2']);

        $result = $this->handler->clear($this->testQueue);
        $this->assertTrue($result);

        // Verify queue is empty
        $job = $this->handler->pop($this->testQueue, ['default']);
        $this->assertNull($job);
    }

    public function testClearNonexistentQueueKeepsHandlerUsable(): void
    {
        $this->assertTrue($this->handler->clear($this->testQueue));

        $result = $this->handler->push($this->testQueue, 'success', ['message' => 'Still usable']);
        $this->assertTrue($result->getStatus());

        $job = $this->handler->pop($this->testQueue, ['default']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('Still usable', $job->payload['data']['message']);
        $this->handler->done($job);
    }

    public function testIncorrectJobHandler(): void
    {
        $this->expectException(QueueException::class);

        $this->handler->push($this->testQueue, 'nonexistent-job', []);
    }

    public function testIncorrectQueueFormat(): void
    {
        $this->expectException(QueueException::class);

        $this->handler->push('invalid queue name!', 'success', []);
    }

    public function testIncorrectPriority(): void
    {
        $this->expectException(QueueException::class);

        $this->config->queuePriorities[$this->testQueue] = ['high', 'low'];

        $this->handler->setPriority('medium')->push($this->testQueue, 'success', []);
    }

    public function testCustomPriorityMapping(): void
    {
        // Define custom priorities for a queue
        $this->config->queuePriorities[$this->customPriorityQueue] = ['urgent', 'normal', 'low'];

        // Test that we can push jobs with custom priorities
        $result1 = $this->handler->setPriority('urgent')->push($this->customPriorityQueue, 'success', ['priority' => 'urgent']);
        $result2 = $this->handler->setPriority('normal')->push($this->customPriorityQueue, 'success', ['priority' => 'normal']);
        $result3 = $this->handler->setPriority('low')->push($this->customPriorityQueue, 'success', ['priority' => 'low']);

        $this->assertTrue($result1->getStatus());
        $this->assertTrue($result2->getStatus());
        $this->assertTrue($result3->getStatus());

        // Should get urgent priority job first
        $job = $this->popEventually($this->customPriorityQueue, ['urgent', 'normal', 'low']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('urgent', $job->payload['data']['priority']);
        $this->handler->done($job);

        // Then normal priority
        $job = $this->handler->pop($this->customPriorityQueue, ['urgent', 'normal', 'low']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('normal', $job->payload['data']['priority']);
        $this->handler->done($job);

        // Finally low priority
        $job = $this->handler->pop($this->customPriorityQueue, ['urgent', 'normal', 'low']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('low', $job->payload['data']['priority']);
        $this->handler->done($job);
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
        $result = $this->handler->pop($this->emptyQueue, ['default']);

        $this->assertNull($result);
    }

    public function testFailedAndKeepJob(): void
    {
        $this->handler->push($this->testQueue, 'success', ['test' => 'data']);
        $queueJob = $this->handler->pop($this->testQueue, ['default']);

        $this->assertInstanceOf(QueueJob::class, $queueJob);

        $err    = new Exception('Sample exception');
        $result = $this->handler->failed($queueJob, $err, true);

        $this->assertTrue($result);

        $this->seeInDatabase('queue_jobs_failed', [
            'queue'      => $this->testQueue,
            'connection' => 'rabbitmq',
        ]);
    }

    public function testFailedAndDontKeepJob(): void
    {
        $this->handler->push($this->testQueue, 'success', ['test' => 'data']);
        $queueJob = $this->handler->pop($this->testQueue, ['default']);

        $this->assertInstanceOf(QueueJob::class, $queueJob);

        $err    = new Exception('Sample exception');
        $result = $this->handler->failed($queueJob, $err, false);

        $this->assertTrue($result);

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'queue'      => $this->testQueue,
            'connection' => 'rabbitmq',
        ]);
    }

    public function testDone(): void
    {
        $this->handler->push($this->testQueue, 'success', ['test' => 'data']);
        $queueJob = $this->handler->pop($this->testQueue, ['default']);

        $this->assertInstanceOf(QueueJob::class, $queueJob);

        $result = $this->handler->done($queueJob);

        // Job is acknowledged and removed from RabbitMQ
        $this->assertTrue($result);
    }

    public function testClearAll(): void
    {
        $this->handler->push($this->testQueue1, 'success', ['test' => 'data1']);
        $this->handler->push($this->testQueue2, 'success', ['test' => 'data2']);

        $job1 = $this->popEventually($this->testQueue1, ['default']);
        $job2 = $this->popEventually($this->testQueue2, ['default']);

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

        // Clear all queues
        $result = $this->handler->clear();
        $this->assertTrue($result);

        // Verify queues are empty by attempting to pop
        $jobAfter1 = $this->handler->pop($this->testQueue1, ['default']);
        $jobAfter2 = $this->handler->pop($this->testQueue2, ['default']);

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

    private function deleteDeclaredResources(): void
    {
        $channel = self::getPrivateProperty($this->handler, 'channel');

        foreach (array_keys(self::getPrivateProperty($this->handler, 'declaredQueues')) as $queue) {
            $channel->queue_delete($queue);
        }

        foreach (array_keys(self::getPrivateProperty($this->handler, 'declaredExchanges')) as $exchange) {
            $channel->exchange_delete($exchange);
        }
    }

    /**
     * @param list<string> $priorities
     */
    private function popEventually(string $queue, array $priorities, float $timeout = 1.0): ?QueueJob
    {
        $deadline = microtime(true) + $timeout;

        do {
            $job = $this->handler->pop($queue, $priorities);
            if ($job !== null) {
                return $job;
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        return null;
    }
}
