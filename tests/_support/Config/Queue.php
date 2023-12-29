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

namespace Tests\Support\Config;

use CodeIgniter\Queue\Config\Queue as BaseQueue;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Queue\Handlers\PredisHandler;
use CodeIgniter\Queue\Handlers\RedisHandler;
use Tests\Support\Jobs\Failure;
use Tests\Support\Jobs\Success;

class Queue extends BaseQueue
{
    /**
     * Default handler.
     */
    public string $defaultHandler = 'database';

    /**
     * Available handlers.
     */
    public array $handlers = [
        'database' => DatabaseHandler::class,
        'redis'    => RedisHandler::class,
        'predis'   => PredisHandler::class,
    ];

    /**
     * Database handler config.
     */
    public array $database = [
        'dbGroup'   => 'default',
        'getShared' => true,
    ];

    /**
     * Redis and Predis handler config.
     */
    public array $redis = [
        'host'     => '127.0.0.1',
        'password' => null,
        'port'     => 6379,
        'timeout'  => 0,
        'database' => 0,
    ];

    /**
     * Predis handler config.
     */
    public array $predis = [
        'scheme'   => 'tcp',
        'host'     => '127.0.0.1',
        'password' => null,
        'port'     => 6379,
        'timeout'  => 5,
        'database' => 0,
        'prefix'   => '',
    ];

    /**
     * Whether to keep the DONE jobs in the queue.
     */
    public bool $keepDoneJobs = false;

    /**
     * Whether to save failed jobs for later review.
     */
    public bool $keepFailedJobs = true;

    /**
     * Default priorities for the queue
     * if different from the "default".
     */
    public array $queueDefaultPriority = [
        'queue' => 'low',
    ];

    /**
     * Valid priorities for the queue,
     * if different from the "default".
     */
    public array $queuePriorities = [
        'queue' => ['high', 'low'],
    ];

    /**
     * Your jobs handlers.
     */
    public array $jobHandlers = [
        'success' => Success::class,
        'failure' => Failure::class,
    ];
}
