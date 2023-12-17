<?php

namespace CodeIgniter\Queue\Entities;

use CodeIgniter\Entity\Entity;

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
