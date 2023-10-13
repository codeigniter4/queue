<?php

namespace Michalsn\CodeIgniterQueue\Commands;

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
     * @var array
     */
    protected $arguments = [
        'queueName' => 'Name of the queue we will work with.',
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
    ];

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        // Read params
        if (! $queue = array_shift($params)) {
            CLI::error('The queueName is not specified.');

            return EXIT_ERROR;
        }

        $startTime = microtime(true);
        $cacheName = sprintf('queue-%s-stop', $queue);

        cache()->save($cacheName, $startTime, MINUTE * 10);

        CLI::write('QueueJob will be stopped after the current job finish', 'yellow');

        return EXIT_SUCCESS;
    }
}
