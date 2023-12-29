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

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class QueueFlush extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Queue';

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
        '-hours' => 'Number of hours.',
        '-queue' => 'Queue name.',
    ];

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        // Read params
        $hours = $params['hours'] ?? CLI::getOption('hours');
        $queue = $params['queue'] ?? CLI::getOption('queue');

        if ($hours !== null) {
            $hours = (int) $hours;
        }

        service('queue')->flush($hours, $queue);

        if ($hours === null) {
            CLI::write(sprintf('All failed jobs has been removed from the queue %s', $queue), 'green');
        } else {
            CLI::write(sprintf('All failed jobs older than %s hours has been removed from the queue %s', $hours, $queue), 'green');
        }

        return EXIT_SUCCESS;
    }
}
