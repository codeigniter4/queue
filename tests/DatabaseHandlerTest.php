<?php

namespace Tests;

use Exception;
use Michalsn\CodeIgniterQueue\Entities\QueueJob;
use Michalsn\CodeIgniterQueue\Enums\Status;
use Michalsn\CodeIgniterQueue\Exceptions\QueueException;
use Michalsn\CodeIgniterQueue\Handlers\DatabaseHandler;
use Michalsn\CodeIgniterQueue\Models\QueueJobFailedModel;
use ReflectionException;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\Database\Seeds\TestQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DatabaseHandlerTest extends TestCase
{
    protected $seed = TestQueueSeeder::class;
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
    public function testPop()
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->pop('queue1');

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
        $result  = $handler->pop('queue123');

        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testLater()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1');

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
        $queueJob = $handler->pop('queue1');

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
        $queueJob = $handler->pop('queue1');

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
        $queueJob = $handler->pop('queue1');

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
        $queueJob = $handler->pop('queue1');

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

    /**
     * @throws ReflectionException
     */
    public function testFlush()
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1');

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
