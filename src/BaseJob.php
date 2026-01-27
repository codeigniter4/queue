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

namespace CodeIgniter\Queue;

use CodeIgniter\Queue\Interfaces\JobInterface;

abstract class BaseJob implements JobInterface
{
    // Retry job after X seconds
    protected int $retryAfter = 60;

    // Number of tries
    protected int $tries = 1;

    public function __construct(protected array $data)
    {
    }

    abstract public function process();

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getTries(): int
    {
        return $this->tries;
    }
}
