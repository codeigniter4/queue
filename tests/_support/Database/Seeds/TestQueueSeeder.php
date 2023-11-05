<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Michalsn\CodeIgniterQueue\Entities\QueueJob;
use Michalsn\CodeIgniterQueue\Entities\QueueJobFailed;
use Michalsn\CodeIgniterQueue\Enums\Status;
use Michalsn\CodeIgniterQueue\Models\QueueJobFailedModel;
use Michalsn\CodeIgniterQueue\Models\QueueJobModel;

class TestQueueSeeder extends Seeder
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
