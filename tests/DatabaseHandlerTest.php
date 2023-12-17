<?php

namespace Tests;

use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Queue\Models\QueueJobFailedModel;
use CodeIgniter\Test\ReflectionHelper;
use Exception;
use ReflectionException;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\Database\Seeds\TestDatabaseQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DatabaseHandlerTest extends TestCase
{
    use ReflectionHelper;

    protected $seed = TestDatabaseQueueSeeder::class;
    private QueueConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);
    }

    public function testDatabaseHandler()
    {
        $handler = new DatabaseHandler($this->config);
        $this->assertInstanceOf(DatabaseHandler::class, $handler);
    }

    public function testPriority()
    {
        $handler = new DatabaseHandler($this->config);
        $handler->setPriority('high');

        $this->assertSame('high', self::getPrivateProperty($handler, 'priority'));
    }

    public function testPriorityNameException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name should consists only lowercase letters.');

        $handler = new DatabaseHandler($this->config);
        $handler->setPriority('high_:');
    }

    public function testPriorityNameLengthException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name is too long. It should be no longer than 64 letters.');

        $handler = new DatabaseHandler($this->config);
        $handler->setPriority(str_repeat('a', 65));
    }

    /**
     * @throws ReflectionException
     */
    public function testPush()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'   => 'queue',
            'payload' => json_encode(['job' => 'success', 'data' => ['key' => 'value']]),
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithPriority()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->setPriority('high')->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'    => 'queue',
            'payload'  => json_encode(['job' => 'success', 'data' => ['key' => 'value']]),
            'priority' => 'high',
        ]);
    }

    public function testPushAndPopWithPriority()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->push('queue', 'success', ['key1' => 'value1']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'    => 'queue',
            'payload'  => json_encode(['job' => 'success', 'data' => ['key1' => 'value1']]),
            'priority' => 'low',
        ]);

        $result = $handler->setPriority('high')->push('queue', 'success', ['key2' => 'value2']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'    => 'queue',
            'payload'  => json_encode(['job' => 'success', 'data' => ['key2' => 'value2']]),
            'priority' => 'high',
        ]);

        $result = $handler->pop('queue', ['high', 'low']);
        $this->assertInstanceOf(QueueJob::class, $result);
        $payload = ['job' => 'success', 'data' => ['key2' => 'value2']];
        $this->assertSame($payload, $result->payload);

        $result = $handler->pop('queue', ['high', 'low']);
        $this->assertInstanceOf(QueueJob::class, $result);
        $payload = ['job' => 'success', 'data' => ['key1' => 'value1']];
        $this->assertSame($payload, $result->payload);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This job name is not defined in the $jobHandlers array.');

        $handler = new DatabaseHandler($this->config);
        $handler->push('queue', 'not-exists', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithPriorityException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This queue has incorrectly defined priority: "invalid" for the queue: "queue".');

        $handler = new DatabaseHandler($this->config);
        $handler->setPriority('invalid')->push('queue', 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithIncorrectQueueFormatException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The queue name should consists only lowercase letters or numbers.');

        $handler = new DatabaseHandler($this->config);
        $handler->push('queue*', 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithTooLongQueueNameException()
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The queue name is too long. It should be no longer than 64 letters.');

        $handler = new DatabaseHandler($this->config);
        $handler->push(str_repeat('a', 65), 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPop()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->pop('queue1', ['default']);

        $this->assertInstanceOf(QueueJob::class, $result);
        $this->seeInDatabase('queue_jobs', [
            'status'       => Status::RESERVED->value,
            'available_at' => 1_697_269_860,
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testPopEmpty()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->pop('queue123', ['default']);

        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testLater()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $this->seeInDatabase('queue_jobs', [
            'id'     => 2,
            'status' => Status::RESERVED->value,
        ]);

        $result = $handler->later($queueJob, 60);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'id'     => 2,
            'status' => Status::PENDING->value,
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testFailedAndKeepJob()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, true);

        $this->assertTrue($result);
        $this->dontSeeInDatabase('queue_jobs', [
            'id' => 2,
        ]);
        $this->seeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'database',
            'queue'      => 'queue1',
        ]);
    }

    public function testFailedAndDontKeepJob()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception');
        $result = $handler->failed($queueJob, $err, false);

        $this->assertTrue($result);
        $this->dontSeeInDatabase('queue_jobs', [
            'id' => 2,
        ]);
        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id'         => 2,
            'connection' => 'database',
            'queue'      => 'queue1',
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testDoneAndKeepJob()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $result = $handler->done($queueJob, true);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'id'     => 2,
            'status' => Status::DONE->value,
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testDoneAndDontKeepJob()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $result = $handler->done($queueJob, false);

        $this->assertTrue($result);
        $this->dontSeeInDatabase('queue_jobs', [
            'id' => 2,
        ]);
    }

    public function testClear()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->clear('queue1');

        $this->assertTrue($result);

        $this->dontSeeInDatabase('queue_jobs', [
            'id' => 1,
        ]);
        $this->dontSeeInDatabase('queue_jobs', [
            'id' => 2,
        ]);
    }

    public function testRetry()
    {
        $handler = new DatabaseHandler($this->config);
        $count   = $handler->retry(1, 'queue1');

        $this->assertSame($count, 1);

        $this->seeInDatabase('queue_jobs', [
            'id'      => 3,
            'queue'   => 'queue1',
            'payload' => json_encode(['job' => 'failure', 'data' => []]),
        ]);
        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
    }

    public function testForget()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->forget(1);

        $this->assertTrue($result);

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
    }

    public function testForgetFalse()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->forget(1111);

        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testFlush()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $err    = new Exception('Sample exception here');
        $result = $handler->failed($queueJob, $err, true);

        $this->assertTrue($result);

        // Set first record as older than 1 hour
        model(QueueJobFailedModel::class)->builder()->where('id', 1)->update(['failed_at' => 1_697_269_860]);

        $handler->flush(1, 'queue1');
        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
        $this->seeInDatabase('queue_jobs_failed', [
            'id' => 2,
        ]);
    }

    public function testFlushAll()
    {
        $handler = new DatabaseHandler($this->config);
        $handler->flush(null, null);
        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
    }

    public function testListFailed()
    {
        $handler = new DatabaseHandler($this->config);
        $list    = $handler->listFailed('queue1');
        $this->assertCount(1, $list);
    }
}
