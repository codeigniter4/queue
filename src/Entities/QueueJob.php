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
 * @property int    $attempts
 * @property Time   $available_at
 * @property Time   $created_at
 * @property int    $id
 * @property array  $payload
 * @property string $priority
 * @property string $queue
 * @property int    $status
 */
class QueueJob extends Entity
{
    protected $dates = ['available_at', 'created_at'];
    protected $casts = [
        'id'       => 'integer',
        'queue'    => 'string',
        'payload'  => 'json-array',
        'priority' => 'string',
        'status'   => 'integer',
        'attempts' => 'integer',
    ];
}
