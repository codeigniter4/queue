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

namespace Tests\Models;

use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Models\QueueJobModel;
use CodeIgniter\Test\ReflectionHelper;
use Tests\Support\Database\Seeds\TestDatabaseQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class QueueJobModelTest extends TestCase
{
    use ReflectionHelper;

    protected $seed = TestDatabaseQueueSeeder::class;

    public function testQueueJobModel(): void
    {
        $model = model(QueueJobModel::class);
        $this->assertInstanceOf(QueueJobModel::class, $model);
    }

    public function testSkipLocked(): void
    {
        $model  = model(QueueJobModel::class);
        $method = $this->getPrivateMethodInvoker($model, 'skipLocked');

        $sql    = 'SELECT * FROM queue_jobs WHERE queue = "test" AND status = 0 AND available_at < 123456 LIMIT 1';
        $result = $method($sql);

        if ($model->db->DBDriver === 'SQLite3') {
            $this->assertSame($sql, $result);
        } elseif ($model->db->DBDriver === 'SQLSRV') {
            $this->assertStringContainsString('WITH (ROWLOCK,UPDLOCK,READPAST) WHERE', (string) $result);
        } else {
            $this->assertStringContainsString('FOR UPDATE SKIP LOCKED', (string) $result);
        }
    }

    public function testSkipLockedFalse(): void
    {
        config('Queue')->database['skipLocked'] = false;

        $model  = model(QueueJobModel::class);
        $method = $this->getPrivateMethodInvoker($model, 'skipLocked');

        $sql    = 'SELECT * FROM queue_jobs WHERE queue = "test" AND status = 0 AND available_at < 123456 LIMIT 1';
        $result = $method($sql);

        $this->assertSame($sql, $result);
    }

    public function testGetFromQueueReservesPendingJob(): void
    {
        $model = model(QueueJobModel::class);

        $job = $model->getFromQueue('queue1', ['default']);

        $this->assertInstanceOf(QueueJob::class, $job);
        $this->assertSame('queue1', $job->queue);
        $this->seeInDatabase('queue_jobs', [
            'id'     => $job->id,
            'status' => Status::RESERVED->value,
        ]);
    }

    public function testGetFromQueueReturnsNullWhenNothingPending(): void
    {
        $model = model(QueueJobModel::class);

        $this->assertNull($model->getFromQueue('queue123', ['default']));
    }

    public function testGetFromQueueOnSQLite3ClaimsEachPendingJobOnce(): void
    {
        $model = model(QueueJobModel::class);

        if ($model->db->DBDriver !== 'SQLite3') {
            $this->markTestSkipped('BEGIN IMMEDIATE claim is SQLite3-only.');
        }

        $model->insert(new QueueJob([
            'queue'        => 'queue1',
            'payload'      => ['job' => 'extra', 'data' => []],
            'priority'     => 'default',
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => 1_697_269_865,
        ]));

        $job1 = $model->getFromQueue('queue1', ['default']);
        $job2 = $model->getFromQueue('queue1', ['default']);

        $this->assertInstanceOf(QueueJob::class, $job1);
        $this->assertInstanceOf(QueueJob::class, $job2);
        $this->assertSame(2, $job1->id);
        $this->assertSame(3, $job2->id);
        $this->assertNull($model->getFromQueue('queue1', ['default']));
        $this->seeInDatabase('queue_jobs', ['id' => 2, 'status' => Status::RESERVED->value]);
        $this->seeInDatabase('queue_jobs', ['id' => 3, 'status' => Status::RESERVED->value]);
    }

    public function testSetPriorityEscapesPriorityValues(): void
    {
        $model   = model(QueueJobModel::class);
        $method  = $this->getPrivateMethodInvoker($model, 'setPriority');
        $builder = $model->builder();

        $priority = [
            'priority_key' => "default' THEN 0 ELSE 1 END --",
            'default',
        ];

        $result = $method($builder, $priority);
        $sql    = (string) $result->getCompiledSelect();

        $this->assertStringContainsString($model->db->escape($priority['priority_key']), $sql);
        $this->assertStringNotContainsString('priority_key', $sql);
    }
}
