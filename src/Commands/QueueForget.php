<?php

declare(strict_types=1);

namespace CodeIgniter\Queue\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class QueueForget extends BaseCommand
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
     * @var array
     */
    protected $arguments = [
        'id' => 'ID of the failed job.',
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

        if (service('queue')->forget($id)) {
            CLI::write(sprintf('Failed job with ID %s has been removed.', $id), 'green');
        } else {
            CLI::write(sprintf('Could not find the failed job with ID %s', $id), 'red');
        }

        return EXIT_SUCCESS;
    }
}
