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

class QueueStop extends BaseCommand
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
    protected $name = 'queue:stop';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Stop a given queue.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:stop <queueName>';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'queueName' => 'Name of the queue we will work with.',
    ];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
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

        $startTime = microtime(true);
        $cacheName = sprintf('queue-%s-stop', $queue);

        cache()->save($cacheName, $startTime, MINUTE * 10);

        CLI::write('Queue will be stopped after the current job finish', 'yellow');

        return EXIT_SUCCESS;
    }
}
