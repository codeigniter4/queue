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

namespace CodeIgniter\Queue\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Exceptions\QueueException;

class QueueFailed extends QueueCommand
{
    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'queue:failed';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Display failed queue jobs.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:failed [options]';

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-queue'  => 'Queue name.',
        '-config' => 'The alternative config file to use. Default value: relies on config(\'Queue\')',
    ];

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        // Read params
        $queue = $params['queue'] ?? CLI::getOption('queue');

        try {
            /** @var QueueConfig $config */
            $config = $this->handleConfig($params);
        } catch (QueueException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        $results = service('queue', $config)->listFailed($queue);

        $thead = ['ID', 'Connection', 'Queue', 'Class', 'Failed At'];
        $tbody = [];

        foreach ($results as $result) {
            $tbody[] = [
                $result->id,
                $result->connection,
                $result->queue,
                $this->getClassName($result->payload['job'], $config),
                $result->failed_at,
            ];
        }

        CLI::table($tbody, $thead);

        return EXIT_SUCCESS;
    }

    /**
     * Get job class name.
     */
    private function getClassName(string $job, QueueConfig $config): string
    {
        return $config->jobHandlers[$job] ?? '';
    }
}
