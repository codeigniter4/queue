<?php

namespace Michalsn\CodeIgniterQueue\Config;

use CodeIgniter\Config\BaseConfig;
use Michalsn\CodeIgniterQueue\Exceptions\QueueException;
use Michalsn\CodeIgniterQueue\Handlers\DatabaseHandler;

class Queue extends BaseConfig
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
    ];

    /**
     * Database handler config.
     */
    public array $database = [
        'dbGroup'   => 'default',
        'getShared' => true,
        'table'     => 'queue_jobs',
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
     * Your jobs handlers.
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
     */
    public function resolveJobClass(string $name): string
    {
        if (! isset($this->jobHandlers[$name])) {
            throw QueueException::forIncorrectJobHandler();
        }

        return $this->jobHandlers[$name];
    }
}
