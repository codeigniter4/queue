<?php

namespace Tests\Support\Config;

use Michalsn\CodeIgniterQueue\Config\Queue as BaseQueue;
use Michalsn\CodeIgniterQueue\Handlers\DatabaseHandler;
use Michalsn\CodeIgniterQueue\Handlers\RedisHandler;
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
