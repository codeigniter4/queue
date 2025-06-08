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

/**
 * Represents the result of a queue push operation.
 */
class QueuePushResult
{
    public function __construct(
        protected readonly bool $success,
        protected readonly ?int $jobId = null,
        protected readonly ?string $error = null,
    ) {
    }

    /**
     * Creates a successful push result.
     */
    public static function success(int $jobId): self
    {
        return new self(true, $jobId);
    }

    /**
     * Creates a failed push result.
     */
    public static function failure(?string $error = null): self
    {
        return new self(false, null, $error);
    }

    /**
     * Returns whether the push operation was successful.
     */
    public function getStatus(): bool
    {
        return $this->success;
    }

    /**
     * Returns the job ID if the push was successful, null otherwise.
     */
    public function getJobId(): ?int
    {
        return $this->jobId;
    }

    /**
     * Returns the error message if the push failed, null otherwise.
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
