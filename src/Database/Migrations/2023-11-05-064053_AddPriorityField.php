<?php

namespace Michalsn\CodeIgniterQueue\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPriorityField extends Migration
{
    public function up()
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

        $this->forge->dropKey('queue_jobs', 'queue_status_available_at');
        $this->forge->addKey(['queue', 'priority', 'status', 'available_at']);
    }

    public function down()
    {
        $this->forge->dropKey('queue_jobs', 'queue_priority_status_available_at');
        $this->forge->addKey(['queue', 'status', 'available_at']);

        $this->forge->dropColumn('queue_jobs', 'priority');
        $this->forge->dropColumn('queue_jobs_failed', 'priority');
    }
}
