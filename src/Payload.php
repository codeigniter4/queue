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

use JsonSerializable;

class Payload implements JsonSerializable
{
    public function __construct(protected string $job, protected array $data)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'job'  => $this->job,
            'data' => $this->data,
        ];
    }
}
