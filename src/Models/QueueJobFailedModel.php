<?php

declare(strict_types=1);

namespace CodeIgniter\Queue\Models;

use CodeIgniter\Model;
use CodeIgniter\Queue\Entities\QueueJobFailed;

class QueueJobFailedModel extends Model
{
    protected $table            = 'queue_jobs_failed';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = QueueJobFailed::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['connection', 'queue', 'payload', 'priority', 'exception'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'failed_at';
    protected $updatedField  = '';

    // Validation
    protected $skipValidation = true;

    // Callbacks
    protected $allowCallbacks = false;
}
