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

namespace CodeIgniter\Queue\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddQueueTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'bigint', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'queue'        => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'payload'      => ['type' => 'text', 'null' => false],
            'status'       => ['type' => 'tinyint', 'unsigned' => true, 'null' => false, 'default' => 0],
            'attempts'     => ['type' => 'tinyint', 'unsigned' => true, 'null' => false, 'default' => 0],
            'available_at' => ['type' => 'int', 'unsigned' => true, 'null' => false],
            'created_at'   => ['type' => 'int', 'unsigned' => true, 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['queue', 'status', 'available_at']);
        $this->forge->createTable('queue_jobs', true);

        $this->forge->addField([
            'id'         => ['type' => 'bigint', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'connection' => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'queue'      => ['type' => 'varchar', 'constraint' => 64, 'null' => false],
            'payload'    => ['type' => 'text', 'null' => false],
            'exception'  => ['type' => 'text', 'null' => false],
            'failed_at'  => ['type' => 'int', 'unsigned' => true, 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('queue');
        $this->forge->createTable('queue_jobs_failed', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('queue_jobs', true);
        $this->forge->dropTable('queue_jobs_failed', true);
    }
}
