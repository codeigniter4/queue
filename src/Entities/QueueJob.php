<?php

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
