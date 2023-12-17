<?php

declare(strict_types=1);

namespace CodeIgniter\Queue\Interfaces;

interface JobInterface
{
    public function __construct(array $data);

    public function process();

    public function getRetryAfter(): int;

    public function getTries(): int;
}
