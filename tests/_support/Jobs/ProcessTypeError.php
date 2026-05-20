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
use TypeError;

class ProcessTypeError extends BaseJob
{
    public function process(): void
    {
        throw new TypeError('Runtime type error during job processing.');
    }
}
