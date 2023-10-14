<?php

namespace Michalsn\CodeIgniterQueue;

abstract class BaseJob
{
    // Retry job after X seconds
    protected int $retryAfter = 60;

    // Number of tries
    protected int $tries = 1;

    public function __construct(protected array $data)
    {
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getTries(): int
    {
        return $this->tries;
    }
}
