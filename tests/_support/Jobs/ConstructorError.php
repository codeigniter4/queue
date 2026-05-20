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

namespace Tests\Support\Jobs;

use CodeIgniter\Queue\BaseJob;
use Error;

class ConstructorError extends BaseJob
{
    public function __construct(array $data)
    {
        parent::__construct($data);

        throw new Error('Runtime error during job construction.');
    }

    public function process(): void
    {
    }
}
