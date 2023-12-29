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

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Entities\QueueJobFailed;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Models\QueueJobFailedModel;
use CodeIgniter\Queue\Models\QueueJobModel;

class TestDatabaseQueueSeeder extends Seeder
{
    public function run(): void
    {
        model(QueueJobModel::class)->insert(new QueueJob([
            'queue'        => 'queue1',
            'payload'      => ['job' => 'success', 'data' => []],
            'priority'     => 'default',
            'status'       => Status::RESERVED->value,
            'attempts'     => 0,
            'available_at' => 1_697_269_864,
        ]));

        model(QueueJobModel::class)->insert(new QueueJob([
            'queue'        => 'queue1',
            'payload'      => ['job' => 'failure', 'data' => []],
            'priority'     => 'default',
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => 1_697_269_860,
        ]));

        model(QueueJobFailedModel::class)->insert(new QueueJobFailed([
            'connection' => 'database',
            'queue'      => 'queue1',
            'payload'    => ['job' => 'failure', 'data' => []],
            'priority'   => 'default',
            'exception'  => 'Exception info',
        ]));
    }
}
