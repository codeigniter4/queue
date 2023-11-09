<?php

namespace Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\ReflectionHelper;
use Exception;
use Michalsn\CodeIgniterQueue\Entities\QueueJob;
use Michalsn\CodeIgniterQueue\Exceptions\QueueException;
use Michalsn\CodeIgniterQueue\Handlers\RedisHandler;
use RedisException;
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

    public function testRedisHandler()
    {
        $handler = new RedisHandler($this->config);
        $this->assertInstanceOf(RedisHandler::class, $handler);
    }

    public function testPriority()
    {
        $handler = new RedisHandler($this->config);
        $handler->setPriority('high');

        $this->assertSame('high', self::getPrivateProperty($handler, 'priority'));
    }

    public function testPriorityException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name should consists only lowercase letters.');

        $handler = new RedisHandler($this->config);
        $handler->setPriority('high_:');
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testPush()
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1, $redis->zCard('queues:queue:low'));

        $task     = $redis->zRangeByScore('queues:queue:low', '-inf', Time::now()->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));
        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key' => 'value'], $queueJob->payload['data']);
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testPushWithPriority()
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->setPriority('high')->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1, $redis->zCard('queues:queue:high'));

        $task     = $redis->zRangeByScore('queues:queue:high', '-inf', Time::now()->timestamp, ['limit' => [0, 1]]);
        $queueJob = new QueueJob(json_decode((string) $task[0], true));
        $this->assertSame('success', $queueJob->payload['job']);
        $this->assertSame(['key' => 'value'], $queueJob->payload['data']);
    }

    /**
     * @throws RedisException
     */
    public function testPushException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This job name is not defined in the $jobHandlers array.');

        $handler = new RedisHandler($this->config);
        $handler->push('queue', 'not-exists', ['key' => 'value']);
    }

    /**
     * @throws RedisException
     */
    public function testPushWithPriorityException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This queue has incorrectly defined priority: "invalid" for the queue: "queue".');

        $handler = new RedisHandler($this->config);
        $handler->setPriority('invalid')->push('queue', 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPop()
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->pop('queue1', ['default']);

        $this->assertInstanceOf(QueueJob::class, $result);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(1234567890654321, $result->id);
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));
        $this->assertTrue($redis->hExists('queues:queue1::reserved', $result->id));
    }

    /**
     * @throws ReflectionException
     */
    public function testPopEmpty()
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->pop('queue123', ['default']);

        $this->assertNull($result);
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testLater()
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertTrue($redis->hExists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $result = $handler->later($queueJob, 60);

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(1, $redis->zCard('queues:queue1:default'));
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testFailedAndKeepJob()
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, true);

        $redis = self::getPrivateProperty($handler, 'redis');

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $this->seeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'database',
            'queue'      => 'queue1',
        ]);
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testFailedAndDontKeepJob()
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, false);

        $redis = self::getPrivateProperty($handler, 'redis');

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'database',
            'queue'      => 'queue1',
        ]);
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testDoneAndKeepJob()
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $result = $handler->done($queueJob, true);

        $redis = self::getPrivateProperty($handler, 'redis');

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(1, $redis->lLen('queues:queue1::done'));
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testDoneAndDontKeepJob()
    {
        $handler  = new RedisHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));

        $result = $handler->done($queueJob, false);

        $this->assertTrue($result);
        $this->assertFalse($redis->hExists('queues:queue1::reserved', $queueJob->id));
        $this->assertSame(0, $redis->lLen('queues:queue1::done'));
    }

    /**
     * @throws RedisException|ReflectionException
     */
    public function testClear()
    {
        $handler = new RedisHandler($this->config);
        $result  = $handler->clear('queue1');

        $this->assertTrue($result);

        $redis = self::getPrivateProperty($handler, 'redis');
        $this->assertSame(0, $redis->zCard('queues:queue1:default'));
    }

    /**
     * @throws ReflectionException
     */
    public function testRetry()
    {
        $handler = new RedisHandler($this->config);
        $count   = $handler->retry(1, 'queue1');

        $this->assertSame($count, 1);

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
