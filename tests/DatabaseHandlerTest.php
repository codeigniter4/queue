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

    public function testDatabaseHandler(): void
    {
        $handler = new DatabaseHandler($this->config);
        $this->assertInstanceOf(DatabaseHandler::class, $handler);
    }

    public function testPriority(): void
    {
        $handler = new DatabaseHandler($this->config);
        $handler->setPriority('high');

        $this->assertSame('high', self::getPrivateProperty($handler, 'priority'));
    }

    public function testPriorityNameException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name should consists only lowercase letters.');

        $handler = new DatabaseHandler($this->config);
        $handler->setPriority('high_:');
    }

    public function testPriorityNameLengthException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The priority name is too long. It should be no longer than 64 letters.');

        $handler = new DatabaseHandler($this->config);
        $handler->setPriority(str_repeat('a', 65));
    }

    /**
     * @throws ReflectionException
     */
    public function testPush(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new DatabaseHandler($this->config);
        $result  = $handler->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'        => 'queue',
            'payload'      => json_encode(['job' => 'success', 'data' => ['key' => 'value']]),
            'available_at' => '1703859316',
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithPriority(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new DatabaseHandler($this->config);
        $result  = $handler->setPriority('high')->push('queue', 'success', ['key' => 'value']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'        => 'queue',
            'payload'      => json_encode(['job' => 'success', 'data' => ['key' => 'value']]),
            'priority'     => 'high',
            'available_at' => '1703859316',
        ]);
    }

    public function testPushAndPopWithPriority(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new DatabaseHandler($this->config);
        $result  = $handler->push('queue', 'success', ['key1' => 'value1']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'        => 'queue',
            'payload'      => json_encode(['job' => 'success', 'data' => ['key1' => 'value1']]),
            'priority'     => 'low',
            'available_at' => '1703859316',
        ]);

        $result = $handler->setPriority('high')->push('queue', 'success', ['key2' => 'value2']);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'queue'        => 'queue',
            'payload'      => json_encode(['job' => 'success', 'data' => ['key2' => 'value2']]),
            'priority'     => 'high',
            'available_at' => '1703859316',
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
    public function testPushException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This job name is not defined in the $jobHandlers array.');

        $handler = new DatabaseHandler($this->config);
        $handler->push('queue', 'not-exists', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithPriorityException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This queue has incorrectly defined priority: "invalid" for the queue: "queue".');

        $handler = new DatabaseHandler($this->config);
        $handler->setPriority('invalid')->push('queue', 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithIncorrectQueueFormatException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The queue name should consists only lowercase letters or numbers.');

        $handler = new DatabaseHandler($this->config);
        $handler->push('queue*', 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPushWithTooLongQueueNameException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('The queue name is too long. It should be no longer than 64 letters.');

        $handler = new DatabaseHandler($this->config);
        $handler->push(str_repeat('a', 65), 'success', ['key' => 'value']);
    }

    /**
     * @throws ReflectionException
     */
    public function testPop(): void
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
    public function testPopEmpty(): void
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->pop('queue123', ['default']);

        $this->assertNull($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testLater(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $this->seeInDatabase('queue_jobs', [
            'id'     => 2,
            'status' => Status::RESERVED->value,
        ]);

        $result = $handler->later($queueJob, 60);

        $this->assertTrue($result);
        $this->seeInDatabase('queue_jobs', [
            'id'           => 2,
            'status'       => Status::PENDING->value,
            'available_at' => Time::now()->addSeconds(60)->timestamp,
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testFailedAndKeepJob(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

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
            'failed_at'  => '1703859316',
        ]);
    }

    public function testFailedAndDontKeepJob(): void
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
    public function testDoneAndKeepJob(): void
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
    public function testDoneAndDontKeepJob(): void
    {
        $handler  = new DatabaseHandler($this->config);
        $queueJob = $handler->pop('queue1', ['default']);

        $result = $handler->done($queueJob, false);

        $this->assertTrue($result);
        $this->dontSeeInDatabase('queue_jobs', [
            'id' => 2,
        ]);
    }

    public function testClear(): void
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

    public function testRetry(): void
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

    public function testForget(): void
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->forget(1);

        $this->assertTrue($result);

        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
    }

    public function testForgetFalse(): void
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->forget(1111);

        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testFlush(): void
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

    public function testFlushAll(): void
    {
        $handler = new DatabaseHandler($this->config);
        $handler->flush(null, null);
        $this->dontSeeInDatabase('queue_jobs_failed', [
            'id' => 1,
        ]);
    }

    public function testListFailed(): void
    {
        $handler = new DatabaseHandler($this->config);
        $list    = $handler->listFailed('queue1');
        $this->assertCount(1, $list);
    }
}
