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
use CodeIgniter\Exceptions\CriticalError;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Entities\QueueJobFailed;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Models\QueueJobFailedModel;
use Redis;
use RedisException;
use ReflectionException;
use Tests\Support\Config\Queue as QueueConfig;

class TestRedisQueueSeeder extends Seeder
{
    /**
     * @throws RedisException|ReflectionException
     */
    public function run(): void
    {
        $redis = new Redis();

        try {
            $config = config(QueueConfig::class);
            $redis->connect($config->redis['host'], ($config->redis['host'][0] === '/' ? 0 : $config->redis['port']), $config->redis['timeout']);
        } catch (RedisException $e) {
            throw new CriticalError('Queue: RedisException occurred with message (' . $e->getMessage() . ').');
        }

        $redis->flushDB();

        $jobQueue = new QueueJob([
            'id'           => '1234567890123456',
            'queue'        => 'queue1',
            'payload'      => ['job' => 'success', 'data' => []],
            'priority'     => 'default',
            'status'       => Status::RESERVED->value,
            'attempts'     => 0,
            'available_at' => 1_697_269_864,
        ]);
        $redis->hSet("queues:{$jobQueue->queue}::reserved", (string) $jobQueue->id, json_encode($jobQueue));

        $jobQueue = new QueueJob([
            'id'           => '1234567890654321',
            'queue'        => 'queue1',
            'payload'      => ['job' => 'failure', 'data' => []],
            'priority'     => 'default',
            'status'       => Status::PENDING->value,
            'attempts'     => 0,
            'available_at' => 1_697_269_860,
        ]);
        $redis->zAdd("queues:{$jobQueue->queue}:{$jobQueue->priority}", $jobQueue->available_at->timestamp, json_encode($jobQueue));

        model(QueueJobFailedModel::class)->insert(new QueueJobFailed([
            'connection' => 'database',
            'queue'      => 'queue1',
            'payload'    => ['job' => 'failure', 'data' => ['failed' => true]],
            'priority'   => 'default',
            'exception'  => 'Exception info',
        ]));
    }
}
