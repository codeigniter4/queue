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

class QueueFlush extends QueueCommand
{
    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'queue:flush';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Flush jobs from failed queues.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:flush [options]';

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-hours'  => 'Number of hours.',
        '-queue'  => 'Queue name.',
        '-config' => 'The alternative config file to use. Default value: relies on config(\'Queue\')',
    ];

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        // Read params
        $hours = $params['hours'] ?? CLI::getOption('hours');
        $queue = $params['queue'] ?? CLI::getOption('queue');

        try {
            /** @var QueueConfig $config */
            $config = $this->handleConfig($params);
        } catch (QueueException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        if ($hours !== null) {
            $hours = (int) $hours;
        }

        service('queue', $config)->flush($hours, $queue);

        if ($hours === null) {
            CLI::write(sprintf('All failed jobs has been removed from the queue %s', $queue), 'green');
        } else {
            CLI::write(sprintf('All failed jobs older than %s hours has been removed from the queue %s', $hours, $queue), 'green');
        }

        return EXIT_SUCCESS;
    }
}
