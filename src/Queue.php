<?php

namespace Michalsn\CodeIgniterQueue;

use Michalsn\CodeIgniterQueue\Config\Queue as QueueConfig;
use Michalsn\CodeIgniterQueue\Exceptions\QueueException;
use Michalsn\CodeIgniterQueue\Interfaces\QueueInterface;

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
