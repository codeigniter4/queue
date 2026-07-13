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

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Validation\ValidationInterface;
use Config\Database;
use ReflectionException;
use Throwable;

class QueueJobModel extends Model
{
    protected $table            = 'queue_jobs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = QueueJob::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['queue', 'payload', 'priority', 'status', 'attempts', 'available_at'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
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

    /**
     * Get the oldest item from the queue.
     *
     * @throws ReflectionException
     */
    public function getFromQueue(string $name, array $priority): ?QueueJob
    {
        // For SQLite3 memory database this will cause problems
        // so check if we're not in the testing environment first.
        if ($this->db->database !== ':memory:' && $this->db->connID !== false) {
            // Make sure we still have the connection
            $this->db->reconnect();
        }

        return match ($this->db->DBDriver) {
            'SQLite3' => $this->popAtomic($name, $priority),
            default   => $this->popWithLock($name, $priority),
        };
    }

    /**
     * Claim the oldest pending job while holding SQLite's write lock.
     *
     * SQLite has no FOR UPDATE SKIP LOCKED. BEGIN IMMEDIATE takes the write
     * lock before the SELECT, so the candidate cannot be claimed by another
     * worker between selecting it and marking it RESERVED.
     *
     * @throws ReflectionException
     */
    private function popAtomic(string $name, array $priority): ?QueueJob
    {
        if ($this->db->simpleQuery('BEGIN IMMEDIATE') === false) {
            return null;
        }

        try {
            $builder = $this->builder()
                ->where('queue', $name)
                ->where('status', Status::PENDING->value)
                ->where('available_at <=', Time::now()->timestamp)
                ->limit(1);

            $builder = $this->setPriority($builder, $priority);
            $sql     = $builder->getCompiledSelect();

            $query = $this->db->query($sql);

            if ($query === false) {
                $this->db->simpleQuery('ROLLBACK');

                return null;
            }

            /** @var QueueJob|null $row */
            $row = $query->getCustomRowObject(0, QueueJob::class);

            if ($row === null) {
                $this->db->simpleQuery('COMMIT');

                return null;
            }

            $this->builder()
                ->where('id', $row->id)
                ->where('status', Status::PENDING->value)
                ->update(['status' => Status::RESERVED->value]);

            $claimed = $this->db->affectedRows() === 1;

            $this->db->simpleQuery('COMMIT');

            return $claimed ? $row : null;
        } catch (Throwable $e) {
            $this->db->simpleQuery('ROLLBACK');

            throw $e;
        }
    }

    /**
     * Claim the oldest pending job inside a transaction, using
     * FOR UPDATE SKIP LOCKED where supported.
     *
     * @throws ReflectionException
     */
    private function popWithLock(string $name, array $priority): ?QueueJob
    {
        // Start transaction
        $this->db->transStart();

        // Prepare SQL
        $builder = $this->builder()
            ->where('queue', $name)
            ->where('status', Status::PENDING->value)
            ->where('available_at <=', Time::now()->timestamp)
            ->limit(1);

        $builder = $this->setPriority($builder, $priority);
        $sql     = $builder->getCompiledSelect();

        $query = $this->db->query($this->skipLocked($sql));
        if ($query === false) {
            $this->db->transComplete();

            return null;
        }
        /** @var QueueJob|null $row */
        $row = $query->getCustomRowObject(0, QueueJob::class);

        if ($row !== null) {
            // Change status
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
        if ($this->db->DBDriver === 'SQLite3' || config('Queue')->database['skipLocked'] === false) {
            return $sql;
        }

        if ($this->db->DBDriver === 'SQLSRV') {
            $replace = 'WITH (ROWLOCK,UPDLOCK,READPAST) WHERE';

            return str_replace('WHERE', $replace, $sql);
        }

        if ($this->db->DBDriver === 'OCI8') {
            $sql = str_replace('SELECT *', 'SELECT "id"', $sql);
            // prepare final query
            $sql = sprintf('SELECT * FROM "%s" WHERE "id" = (%s)', $this->db->prefixTable($this->table), $sql);
        }

        return $sql . ' FOR UPDATE SKIP LOCKED';
    }

    /**
     * Handle priority of the queue.
     */
    private function setPriority(BaseBuilder $builder, array $priority): BaseBuilder
    {
        $priority = array_values($priority);

        $builder->whereIn('priority', $priority);

        if ($priority !== ['default']) {
            $escapedPriority = array_map($this->db->escape(...), $priority);

            if ($this->db->DBDriver !== 'MySQLi') {
                $builder->orderBy(
                    sprintf('CASE %s ', $this->db->protectIdentifiers('priority'))
                    . implode(
                        ' ',
                        array_map(static fn ($value, $key) => "WHEN {$value} THEN {$key}", $escapedPriority, array_keys($escapedPriority)),
                    )
                    . ' END',
                    '',
                    false,
                );
            } else {
                $builder->orderBy(
                    'FIELD(priority, '
                    . implode(',', $escapedPriority)
                    . ')',
                    '',
                    false,
                );
            }
        }

        $builder
            ->orderBy('available_at', 'asc')
            ->orderBy('id', 'asc');

        return $builder;
    }
}
