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

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Handlers\RedisHandler;
use CodeIgniter\Test\ReflectionHelper;
use Exception;
use ReflectionException;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\Database\Seeds\TestRedisQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class RedisHandlerTest extends TestCase
{
    use ReflectionHelper;

    protected $seed = TestRedisQueueSeeder::class;
    private QueueConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);
    }

    public function testRedisHandler(): void
    {
        $handler = new RedisHandler($this->config);
        $this->assertInstanceOf(RedisHandler::class, $handler);
    }

    public function testPriority(): void
    {
        $handler = new RedisHandler($this->config);
        $handler->setPriority('high');

        $this->assertSame('high', self::getPrivateProperty($handler, 'priority'));
    }

    public function testPriorityException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name should consists only lowercase letters.');

        $handler = new RedisHandler($this->config);
        $handler->setPriority('high_:');
    }

    public function testPush(): void
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result->getStatus());

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1, $redis->zCard('queues:queue:low'));

        $task     = $redis->zRangeByScore('queues:queue:low', '-inf', Time::now()->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));
        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key' => 'value'], $queueJob->payload['data']);
        $this->assertSame([], $queueJob->payload['metadata']);
    }

    public function testPushWithPriority(): void
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->setPriority('high')->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result->getStatus());

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1, $redis->zCard('queues:queue:high'));

        $task     = $redis->zRangeByScore('queues:queue:high', '-inf', Time::now()->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));
        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key' => 'value'], $queueJob->payload['data']);
        $this->assertSame([], $queueJob->payload['metadata']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithDelay(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new RedisHandler($this->config);
        $result  = $handler->setDelay(MINUTE)->push('queue-delay', 'success', ['key' => 'value']);

        $this->assertTrue($result->getStatus());

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1, $redis->zCard('queues:queue-delay:default'));

        $task     = $redis->zRangeByScore('queues:queue-delay:default', '-inf', Time::now()->addSeconds(MINUTE)->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));
        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key' => 'value'], $queueJob->payload['data']);
        $this->assertSame([], $queueJob->payload['metadata']);
    }

    /**
     * @throws Exception
     */
    public function testChain(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new RedisHandler($this->config);
        $result  = $handler->chain(static function ($chain): void {
            $chain
                ->push('queue', 'success', ['key1' => 'value1'])
                ->push('queue', 'success', ['key2' => 'value2']);
        });

        $this->assertTrue($result->getStatus());

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1, $redis->zCard('queues:queue:low'));

        $task     = $redis->zRangeByScore('queues:queue:low', '-inf', Time::now()->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));

        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key1' => 'value1'], $queueJob->payload['data']);
        $this->assertArrayHasKey('metadata', $queueJob->payload);
        $this->assertArrayHasKey('queue', $queueJob->payload['metadata']);
        $this->assertSame('queue', $queueJob->payload['metadata']['queue']);
        $this->assertArrayHasKey('chainedJobs', $queueJob->payload['metadata']);

        $chainedJobs = $queueJob->payload['metadata']['chainedJobs'];
        $this->assertCount(1, $chainedJobs);
        $this->assertSame('success', $chainedJobs[0]['job']);
        $this->assertSame(['key2' => 'value2'], $chainedJobs[0]['data']);
        $this->assertSame('queue', $chainedJobs[0]['metadata']['queue']);
    }

    /**
     * @throws Exception
     */
    public function testChainWithPriorityAndDelay(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new RedisHandler($this->config);
        $result  = $handler->chain(static function ($chain): void {
            $chain
                ->push('queue', 'success', ['key1' => 'value1'])
                ->setPriority('high')
                ->setDelay(60)
                ->push('queue', 'success', ['key2' => 'value2'])
                ->setPriority('low')
                ->setDelay(120);
        });

        $this->assertTrue($result->getStatus());

        $redis = self::getPrivateProperty($handler, 'redis');
        // Should be in high priority queue
        $this->assertSame(1, $redis->zCard('queues:queue:high'));

        // Check with delay
        $task     = $redis->zRangeByScore('queues:queue:high', '-inf', Time::now()->addSeconds(61)->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));

        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key1' => 'value1'], $queueJob->payload['data']);
        $this->assertArrayHasKey('metadata', $queueJob->payload);

        // Check metadata
        $metadata = $queueJob->payload['metadata'];
        $this->assertSame('queue', $metadata['queue']);
        $this->assertSame('high', $metadata['priority']);
        $this->assertSame(60, $metadata['delay']);

        // Check a chained job with its priority and delay
        $this->assertArrayHasKey('chainedJobs', $metadata);
        $chainedJobs = $metadata['chainedJobs'];
        $this->assertCount(1, $chainedJobs);
        $this->assertSame('success', $chainedJobs[0]['job']);
        $this->assertSame(['key2' => 'value2'], $chainedJobs[0]['data']);
        $this->assertSame('queue', $chainedJobs[0]['metadata']['queue']);
        $this->assertSame('low', $chainedJobs[0]['metadata']['priority']);
        $this->assertSame(120, $chainedJobs[0]['metadata']['delay']);
    }

    public function testPushException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This job name is not defined in the $jobHandlers array.');

        $handler = new RedisHandler($this->config);
        $handler->push('queue', 'not-exists', ['key' => 'value']);
    }

    public function testPushWithPriorityException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This queue has incorrectly defined priority: "invalid" for the queue: "queue".');

        $handler = new RedisHandler($this->config);
        $handler->setPriority('invalid')->push('queue', 'success', ['key' => 'value']);
    }

    public function testPop(): void
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->pop('queue1', ['default']);

        $this->assertInstanceOf(QueueJob::class, $result);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1_234_567_890_654_321, $result->id);
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));
        $this->assertTrue($redis->hExists('queues:queue1::reserved', (string) $result->id));
    }

    public function testPopEmpty(): void
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->pop('queue123', ['default']);

        $this->assertNull($result);
    }

    public function testLater(): void
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertTrue($redis->hExists('queues:queue1::reserved', (string) $queueJob->id));
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $result = $handler->later($queueJob, 60);

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', (string) $queueJob->id));
        $this->assertSame(1, $redis->zCard('queues:queue1:default'));
    }

    public function testFailedAndKeepJob(): void
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, true);

        $redis = self::getPrivateProperty($handler, 'redis');

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', (string) $queueJob->id));
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $this->seeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'redis',
            'queue'      => 'queue1',
        ]);
    }

    public function testFailedAndDontKeepJob(): void
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, false);

        $redis = self::getPrivateProperty($handler, 'redis');

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', (string) $queueJob->id));
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'redis',
            'queue'      => 'queue1',
        ]);
    }

    public function testDone(): void
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $result = $handler->done($queueJob);

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', (string) $queueJob->id));
    }

    public function testClear(): void
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->clear('queue1');

        $this->assertTrue($result);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $result = $handler->clear('queue1');
        $this->assertTrue($result);
    }

    public function testClearAll(): void
    {
        $handler = new RedisHandler($this->config);

        $result = $handler->clear();
        $this->assertTrue($result);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertCount(0, $redis->keys('queues:*'));

        $result = $handler->clear();
        $this->assertTrue($result);
    }

    public function testRetry(): void
    {
        $handler = new RedisHandler($this->config);
        $count   = $handler->retry(1, 'queue1');

        $this->assertSame(1, $count);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(2, $redis->zCard('queues:queue1:default'));

        $task     = $redis->zRangeByScore('queues:queue1:default', '-inf', Time::now()->timestamp, ['limit' => [0, 2]]);
        $queueJob = new QueueJob(json_decode((string) $task[1], true));
        $this->assertSame('failure', $queueJob->payload['job']);
        $this->assertSame(['failed' => true], $queueJob->payload['data']);

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
    }
}
