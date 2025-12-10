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
use CodeIgniter\Queue\Handlers\RabbitMQHandler;
use CodeIgniter\Queue\QueuePushResult;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\TestCase;
use Throwable;

/**
 * Test RabbitMQ delay functionality with real timing.
 *
 * @internal
 */
final class RabbitMQDelayTest extends TestCase
{
    private ?RabbitMQHandler $handler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $config = config(QueueConfig::class);

        // Skip tests if RabbitMQ is not available
        if (! $this->isRabbitMQAvailable()) {
            $this->markTestSkipped('RabbitMQ is not available for testing');
        }

        try {
            $this->handler = new RabbitMQHandler($config);
        } catch (CriticalError) {
            $this->markTestSkipped('Cannot connect to RabbitMQ server');
        }
    }

    protected function tearDown(): void
    {
        if ($this->handler !== null) {
            // Clear test queues
            try {
                $this->handler->clear('delay-test-queue');
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    public function testDelayedMessageWithRealTiming(): void
    {
        // Use a short real delay (2 seconds) for testing
        $delaySeconds = 2;
        $startTime    = time();

        // Push a delayed job
        $result = $this->handler->setDelay($delaySeconds)->push('delay-test-queue', 'success', ['type' => 'delayed']);
        $this->assertInstanceOf(QueuePushResult::class, $result);
        $this->assertTrue($result->getStatus());

        // Push an immediate job
        $result = $this->handler->push('delay-test-queue', 'success', ['type' => 'immediate']);
        $this->assertInstanceOf(QueuePushResult::class, $result);
        $this->assertTrue($result->getStatus());

        // Should get immediate job first
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('immediate', $job->payload['data']['type']);
        $this->handler->done($job);

        // Should not get delayed job yet (within first second)
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertNull($job);

        // Wait for delay to expire (with a small buffer)
        $waitTime = $delaySeconds + 1;
        sleep($waitTime);

        // Should now get the delayed job
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('delayed', $job->payload['data']['type']);

        // Verify timing - job should have been delayed at least the specified time
        $elapsedTime = time() - $startTime;
        $this->assertGreaterThanOrEqual($delaySeconds, $elapsedTime);

        // Clean up
        $this->handler->done($job);
    }

    public function testMultipleDelayedJobsWithDifferentDelays(): void
    {
        // Push jobs with different delays
        $result1 = $this->handler->setDelay(1)->push('delay-test-queue', 'success', ['order' => 'first', 'delay' => 1]);
        $result2 = $this->handler->setDelay(3)->push('delay-test-queue', 'success', ['order' => 'second', 'delay' => 3]);
        $result3 = $this->handler->push('delay-test-queue', 'success', ['order' => 'immediate', 'delay' => 0]);

        $this->assertTrue($result1->getStatus());
        $this->assertTrue($result2->getStatus());
        $this->assertTrue($result3->getStatus());

        // Should get immediate job first
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('immediate', $job->payload['data']['order']);
        $this->handler->done($job);

        // Wait 2 seconds - should get first delayed job
        sleep(2);
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('first', $job->payload['data']['order']);
        $this->handler->done($job);

        // Should not get second job yet
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertNull($job);

        // Wait another 2 seconds - should get second delayed job
        sleep(2);
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('second', $job->payload['data']['order']);
        $this->handler->done($job);
    }

    public function testZeroDelayWorksImmediately(): void
    {
        // Jobs with 0 delay should work immediately
        $result = $this->handler->setDelay(0)->push('delay-test-queue', 'success', ['type' => 'zero-delay']);
        $this->assertTrue($result->getStatus());

        // Should be able to pop immediately
        $job = $this->handler->pop('delay-test-queue', ['default']);
        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('zero-delay', $job->payload['data']['type']);

        $this->handler->done($job);
    }

    /**
     * Check if RabbitMQ is available for testing.
     */
    private function isRabbitMQAvailable(): bool
    {
        return class_exists(AMQPConnectionFactory::class);
    }
}
