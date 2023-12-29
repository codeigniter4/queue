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

namespace CodeIgniter\Queue\Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Queue\Handlers\PredisHandler;
use CodeIgniter\Queue\Handlers\RedisHandler;
use CodeIgniter\Queue\Interfaces\JobInterface;
use CodeIgniter\Queue\Interfaces\QueueInterface;

class Queue extends BaseConfig
{
    /**
     * Default handler.
     */
    public string $defaultHandler = 'database';

    /**
     * Available handlers.
     *
     * @var array<string, class-string<QueueInterface>>
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
     * Redis handler config.
     */
    public array $redis = [
        'host'     => '127.0.0.1',
        'password' => null,
        'port'     => 6379,
        'timeout'  => 0,
        'database' => 0,
        'prefix'   => '',
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
    public array $queueDefaultPriority = [];

    /**
     * Valid priorities in the order for the queue,
     * if different from the "default".
     */
    public array $queuePriorities = [];

    /**
     * Your jobs handlers.
     *
     * @var array<string, class-string<JobInterface>>
     */
    public array $jobHandlers = [];

    public function __construct()
    {
        parent::__construct();

        if (ENVIRONMENT === 'testing') {
            $this->database['dbGroup'] = config('database')->defaultGroup;
        }
    }

    /**
     * Resolve job class name.
     *
     * @return class-string<JobInterface>
     */
    public function resolveJobClass(string $name): string
    {
        if (! isset($this->jobHandlers[$name])) {
            throw QueueException::forIncorrectJobHandler();
        }

        return $this->jobHandlers[$name];
    }

    /**
     * Stringify queue priorities.
     */
    public function getQueuePriorities(string $name): ?string
    {
        if (! isset($this->queuePriorities[$name])) {
            return null;
        }

        return implode(',', $this->queuePriorities[$name]);
    }
}
