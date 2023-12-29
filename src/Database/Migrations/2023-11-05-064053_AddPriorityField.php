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

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

/**
 * @property BaseConnection $db
 */
class AddPriorityField extends Migration
{
    public function up(): void
    {
        $fields = [
            'priority' => [
                'type'       => 'varchar',
                'constraint' => 64,
                'null'       => false,
                'default'    => 'default',
                'after'      => 'payload',
            ],
        ];

        $this->forge->addColumn('queue_jobs', $fields);
        $this->forge->addColumn('queue_jobs_failed', $fields);

        // Ugly fix for dropping the correct index
        // since it had no name given
        $keys = $this->db->getIndexData('queue_jobs');

        foreach ($keys as $key) {
            if ($key->fields === ['queue', 'status', 'available_at']) {
                $this->forge->dropKey('queue_jobs', $key->name, false);
                break;
            }
        }

        $this->forge->addKey(['queue', 'priority', 'status', 'available_at'], false, false, 'queue_priority_status_available_at');
        $this->forge->processIndexes('queue_jobs');
    }

    public function down(): void
    {
        // Ugly fix for dropping the correct index
        $keys = $this->db->getIndexData('queue_jobs');

        foreach ($keys as $key) {
            if ($key->fields === ['queue', 'priority', 'status', 'available_at']) {
                $this->forge->dropKey('queue_jobs', $key->name, false);
                break;
            }
        }

        $this->forge->addKey(['queue', 'status', 'available_at']);
        $this->forge->processIndexes('queue_jobs');

        $this->forge->dropColumn('queue_jobs', 'priority');
        $this->forge->dropColumn('queue_jobs_failed', 'priority');
    }
}
