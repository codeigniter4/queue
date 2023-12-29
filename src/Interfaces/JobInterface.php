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

interface JobInterface
{
    public function __construct(array $data);

    public function process();

    public function getRetryAfter(): int;

    public function getTries(): int;
}
