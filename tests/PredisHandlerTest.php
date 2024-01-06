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

namespace ThirdParty\queue\tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Handlers\PredisHandler;
use CodeIgniter\Test\ReflectionHelper;
use Exception;
use ReflectionException;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\Database\Seeds\TestRedisQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class PredisHandlerTest extends TestCase
{
    use ReflectionHelper;

    protected $seed = TestRedisQueueSeeder::class;
    private QueueConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);
    }

    public function testPredisHandler(): void
    {
        $handler = new PredisHandler($this->config);
        $this->assertInstanceOf(PredisHandler::class, $handler);
    }

    public function testPriority(): void
    {
        $handler = new PredisHandler($this->config);
        $handler->setPriority('high');

        $this->assertSame('high', self::getPrivateProperty($handler, 'priority'));
    }

    public function testPriorityException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name should consists only lowercase letters.');

        $handler = new PredisHandler($this->config);
        $handler->setPriority('high_:');
    }

    /**
     * @throws ReflectionException
     */
    public function testPush(): void
    {
        $handler = new PredisHandler($this->config);
        $result  = $handler->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertSame(1, $predis->zcard('queues:queue:low'));

        $task     = $predis->zrangebyscore('queues:queue:low', '-inf', Time::now()->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));
        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key' => 'value'], $queueJob->payload['data']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithPriority(): void
    {
        $handler = new PredisHandler($this->config);
        $result  = $handler->setPriority('high')->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertSame(1, $predis->zcard('queues:queue:high'));

        $task     = $predis->zrangebyscore('queues:queue:high', '-inf', Time::now()->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));
        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key' => 'value'], $queueJob->payload['data']);
    }

    public function testPushException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This job name is not defined in the $jobHandlers array.');

        $handler = new PredisHandler($this->config);
        $handler->push('queue', 'not-exists', ['key' => 'value']);
    }

    public function testPushWithPriorityException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This queue has incorrectly defined priority: "invalid" for the queue: "queue".');

        $handler = new PredisHandler($this->config);
        $handler->setPriority('invalid')->push('queue', 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPop(): void
    {
        $handler = new PredisHandler($this->config);
        $result  = $handler->pop('queue1', ['default']);

        $this->assertInstanceOf(QueueJob::class, $result);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertSame(1_234_567_890_654_321, $result->id);
        $this->assertSame(0, $predis->zcard('queues:queue1:default'));
        $this->assertSame(1, $predis->hexists('queues:queue1::reserved', $result->id));
    }

    public function testPopEmpty(): void
    {
        $handler = new PredisHandler($this->config);
        $result  = $handler->pop('queue123', ['default']);

        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testLater(): void
    {
        $handler  = new PredisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertSame(1, $predis->hexists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $predis->zcard('queues:queue1:default'));

        $result = $handler->later($queueJob, 60);

        $this->assertTrue($result);
        $this->assertSame(0, $predis->hexists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(1, $predis->zcard('queues:queue1:default'));
    }

    /**
     * @throws ReflectionException
     */
    public function testFailedAndKeepJob(): void
    {
        $handler  = new PredisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, true);

        $predis = self::getPrivateProperty($handler, 'predis');

        $this->assertTrue($result);
        $this->assertSame(0, $predis->hexists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $predis->zcard('queues:queue1:default'));

        $this->seeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'predis',
            'queue'      => 'queue1',
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testFailedAndDontKeepJob(): void
    {
        $handler  = new PredisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, false);

        $predis = self::getPrivateProperty($handler, 'predis');

        $this->assertTrue($result);
        $this->assertSame(0, $predis->hexists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $predis->zcard('queues:queue1:default'));

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'predis',
            'queue'      => 'queue1',
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testDoneAndKeepJob(): void
    {
        $handler  = new PredisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $result = $handler->done($queueJob, true);

        $predis = self::getPrivateProperty($handler, 'predis');

        $this->assertTrue($result);
        $this->assertSame(0, $predis->hexists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(1, $predis->llen('queues:queue1::done'));
    }

    /**
     * @throws ReflectionException
     */
    public function testDoneAndDontKeepJob(): void
    {
        $handler  = new PredisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertSame(0, $predis->zcard('queues:queue1:default'));

        $result = $handler->done($queueJob, false);

        $this->assertTrue($result);
        $this->assertSame(0, $predis->hexists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $predis->llen('queues:queue1::done'));
    }

    /**
     * @throws ReflectionException
     */
    public function testClear(): void
    {
        $handler = new PredisHandler($this->config);
        $result  = $handler->clear('queue1');

        $this->assertTrue($result);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertSame(0, $predis->zcard('queues:queue1:default'));

        $result = $handler->clear('queue1');
        $this->assertTrue($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testClearAll(): void
    {
        $handler = new PredisHandler($this->config);
        $result  = $handler->clear();

        $this->assertTrue($result);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertCount(0, $predis->keys('queues:*'));

        $result = $handler->clear();
        $this->assertTrue($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testRetry(): void
    {
        $handler = new PredisHandler($this->config);
        $count   = $handler->retry(1, 'queue1');

        $this->assertSame($count, 1);

        $predis = self::getPrivateProperty($handler, 'predis');
        $this->assertSame(2, $predis->zcard('queues:queue1:default'));

        $task     = $predis->zrangebyscore('queues:queue1:default', '-inf', Time::now()->timestamp, ['limit' => [0, 2]]);
        $queueJob = new QueueJob(json_decode((string) $task[1], true));
        $this->assertSame('failure', $queueJob->payload['job']);
        $this->assertSame(['failed' => true], $queueJob->payload['data']);

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
    }
}
