<?php

namespace Michalsn\CodeIgniterQueue\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Michalsn\CodeIgniterQueue\Config\Queue as QueueConfig;

class QueueFailed extends BaseCommand
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
    protected $name = 'queue:failed';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Display failed queue jobs.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:failed [options]';

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
        $queue = $params['queue'] ?? CLI::getOption('queue');

        /** @var QueueConfig $config */
        $config = config('queue');

        $results = service('queue')->listFailed($queue);

        $thead = ['ID', 'Connection', 'Queue', 'Class', 'Failed At'];
        $tbody = [];

        foreach ($results as $result) {
            $tbody[] = [$result->id, $result->connection, $result->queue, $this->getClassName($result->payload['job'], $config), $result->failed_at];
        }

        CLI::table($tbody, $thead);

        return EXIT_SUCCESS;
    }

    /**
     * Get job class name.
     */
    private function getClassName(string $job, QueueConfig $config): string
    {
        return $config->jobHandlers[$job] ?? '';
    }
}
