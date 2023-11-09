<?php

namespace Michalsn\CodeIgniterQueue\Entities;

use CodeIgniter\Entity\Entity;

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
