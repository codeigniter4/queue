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

namespace CodeIgniter\Queue\Models;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Queue\Entities\QueueJobFailed;
use CodeIgniter\Validation\ValidationInterface;
use Config\Database;

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

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        $this->DBGroup = config('Queue')->database['dbGroup'];

        $db ??= Database::connect($this->DBGroup);
        assert($db instanceof BaseConnection);

        // Turn off the Strict Mode
        $db->transStrict(false);

        parent::__construct($db, $validation);
    }
}
