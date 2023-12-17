<?php

namespace CodeIgniter\Queue\Interfaces;

interface JobInterface
{
    public function __construct(array $data);

    public function process();

    public function getRetryAfter(): int;

    public function getTries(): int;
}
