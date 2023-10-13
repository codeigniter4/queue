<?php

namespace Michalsn\CodeIgniterQueue\Interfaces;

interface QueueInterface
{
    public function push(string $queue, string $job, array $data);

    public function pop(string $queue);
}
