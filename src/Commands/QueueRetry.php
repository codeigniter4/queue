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

class QueueRetry extends QueueCommand
{
    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'queue:retry';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Retry one job or all jobs from failed queues.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:retry <id> [options]';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'id' => 'ID of the failed job or "all" for all failed jobs.',
    ];

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
        $id = array_shift($params);
        if ($id === null) {
            CLI::error('The ID of the failed job is not specified.');

            return EXIT_ERROR;
        }

        try {
            /** @var QueueConfig $config */
            $config = $this->handleConfig($params);
        } catch (QueueException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        $id = $id === 'all' ? null : (int) $id;

        $queue = $params['queue'] ?? CLI::getOption('queue');

        $count = service('queue', $config)->retry($id, $queue);

        if ($count === 0) {
            CLI::write(sprintf('No failed jobs has been restored to the queue %s', $queue), 'red');
        } else {
            CLI::write(sprintf('%s failed job(s) has been restored to the queue %s', $count, $queue), 'green');
        }

        return EXIT_SUCCESS;
    }
}
