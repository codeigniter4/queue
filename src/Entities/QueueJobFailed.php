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

namespace CodeIgniter\Queue\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;

/**
 * @property int    $id
 * @property string $connection
 * @property string $queue
 * @property array  $payload
 * @property string $priority
 * @property string $exceptions
 * @property Time   $failed_at
 */
class QueueJobFailed extends Entity
{
    protected $dates = ['failed_at'];
    protected $casts = [
        'id'         => 'integer',
        'connection' => 'string',
        'queue'      => 'string',
        'payload'    => 'json-array',
        'priority'   => 'string',
        'exceptions' => 'string',
    ];
}
