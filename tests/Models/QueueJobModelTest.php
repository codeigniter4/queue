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

use CodeIgniter\Queue\Models\QueueJobModel;
use CodeIgniter\Test\ReflectionHelper;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class QueueJobModelTest extends TestCase
{
    use ReflectionHelper;

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
}
