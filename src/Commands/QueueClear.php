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

class QueueClear extends QueueCommand
{
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
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-config' => 'The alternative config file to use. Default value: relies on config(\'Queue\')',
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

        try {
            /** @var QueueConfig $config */
            $config = $this->handleConfig($params);
        } catch (QueueException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        service('queue', $config)->clear($queue);

        CLI::print('Queue ', 'yellow');
        CLI::print($queue, 'light_yellow');
        CLI::print(' has been cleared.', 'yellow');

        return EXIT_SUCCESS;
    }
}
