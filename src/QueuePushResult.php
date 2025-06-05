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

class QueuePushResult
{
    public function __construct(
        protected readonly bool $success,
        protected readonly ?int $jobId = null,
        protected readonly ?string $error = null,
    ) {
    }

    public static function success(int $jobId): self
    {
        return new self(true, $jobId);
    }

    public static function failure(?string $error = null): self
    {
        return new self(false, null, $error);
    }

    public function getStatus(): bool
    {
        return $this->success;
    }

    public function getJobId(): ?int
    {
        return $this->jobId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
