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

class QueueForget extends QueueCommand
{
    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'queue:forget';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Remove ID from failed job queue.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:forget <id>';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'id' => 'ID of the failed job.',
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

        if (service('queue', $config)->forget((int) $id)) {
            CLI::write(sprintf('Failed job with ID %s has been removed.', $id), 'green');
        } else {
            CLI::write(sprintf('Could not find the failed job with ID %s', $id), 'red');
        }

        return EXIT_SUCCESS;
    }
}
