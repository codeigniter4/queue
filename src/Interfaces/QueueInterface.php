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

namespace CodeIgniter\Queue\Interfaces;

use Closure;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use CodeIgniter\Queue\QueuePushResult;
use Throwable;

interface QueueInterface
{
    public function push(string $queue, string $job, array $data, ?PayloadMetadata $metadata = null): QueuePushResult;

    public function pop(string $queue, array $priorities): ?QueueJob;

    public function later(QueueJob $queueJob, int $seconds): bool;

    public function failed(QueueJob $queueJob, Throwable $err, bool $keepJob): bool;

    public function done(QueueJob $queueJob): bool;

    public function clear(?string $queue = null): bool;

    public function retry(?int $id, ?string $queue): int;

    public function forget(int $id): bool;

    public function flush(?int $hours, ?string $queue): bool;

    public function listFailed(?string $queue): array;

    public function setDelay(int $delay): static;

    public function setPriority(string $priority): static;

    public function chain(Closure $callback): QueuePushResult;
}
