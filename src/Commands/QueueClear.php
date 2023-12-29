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

class QueueClear extends BaseCommand
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
    protected $name = 'queue:clear';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Clear all jobs from a given queue.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:clear <queueName>';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'queueName' => 'Name of the queue we will work with.',
    ];

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        // Read params
        $queue = array_shift($params);
        if ($queue === null) {
            CLI::error('The queueName is not specified.');

            return EXIT_ERROR;
        }

        service('queue')->clear($queue);

        CLI::print('Queue ', 'yellow');
        CLI::print($queue, 'light_yellow');
        CLI::print(' has been cleared.', 'yellow');

        return EXIT_SUCCESS;
    }
}
