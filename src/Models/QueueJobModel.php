<?php

namespace Michalsn\CodeIgniterQueue\Models;

use CodeIgniter\I18n\Time;
use Michalsn\CodeIgniterQueue\Entities\QueueJob;
use CodeIgniter\Model;
use Michalsn\CodeIgniterQueue\Enums\Status;
use ReflectionException;

class QueueJobModel extends Model
{
    protected $table            = '';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = QueueJob::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['queue', 'payload', 'status', 'attempts', 'available_at'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    // Validation
    protected $skipValidation = true;

    // Callbacks
    protected $allowCallbacks = false;

    /**
     * Get the oldest item from the queue.
     *
     * @throws ReflectionException
     */
    public function getFromQueue(string $name): ?QueueJob
    {
        // Make sure we still have the connection
        $this->db->reconnect();
        // Start transaction
        $this->db->transStart();

        // Prepare SQL
        $sql = $this->builder()
            ->where('queue', $name)
            ->where('status', Status::PENDING->value)
            ->where('available_at <=', Time::now()->timestamp)
            ->orderBy('available_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(1)
            ->getCompiledSelect();

        $query = $this->db->query($this->skipLocked($sql));
        /** @var QueueJob $row */
        $row = $query->getCustomRowObject(0, QueueJob::class);

        // Remove row
        if ($row !== null) {
            $this->update($row->id, ['status' => Status::RESERVED->value]);
        }
        // Complete transaction
        $this->db->transComplete();

        return $row;
    }

    /**
     * Skip locked if DB driver support it.
     */
    private function skipLocked(string $sql): string
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return $sql;
        }

        if ($this->db->DBDriver === 'SQLSRV') {
            $replace = 'WITH (ROWLOCK,UPDLOCK,READPAST) WHERE';
            return str_replace('WHERE', $replace, $sql);
        }

        return $sql .= ' FOR UPDATE SKIP LOCKED';
    }
}
