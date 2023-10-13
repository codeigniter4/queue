<?php

namespace Michalsn\CodeIgniterQueue\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class QueueRetry extends BaseCommand
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
     * @var array
     */
    protected $arguments = [
        'id' => 'ID of the failed job or "all" for all failed jobs.',
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '-queue' => 'Queue name.',
    ];

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        // Read params
        if (! $id = array_shift($params)) {
            CLI::error('The ID of the failed job is not specified.');

            return EXIT_ERROR;
        }

        if ($id === 'all') {
            $id = null;
        }

        $queue = $params['queue'] ?? CLI::getOption('queue');

        $count = service('queue')->retry($id, $queue);

        if ($count === 0) {
            CLI::write(sprintf('No failed jobs has been restored to the queue %s', $queue), 'red');
        } else {
            CLI::write(sprintf('%s failed job(s) has been restored to the queue %s', $count, $queue), 'green');
        }

        return EXIT_SUCCESS;
    }
}
