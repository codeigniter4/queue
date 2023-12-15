<?php

namespace CodeIgniter\Queue;

use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Interfaces\QueueInterface;

class Queue
{
    public function __construct(protected QueueConfig $config)
    {
        if (! isset($config->handlers[$config->defaultHandler])) {
            throw QueueException::forIncorrectHandler();
        }
    }

    public function init(): QueueInterface
    {
        return new $this->config->handlers[$this->config->defaultHandler]($this->config);
    }
}
