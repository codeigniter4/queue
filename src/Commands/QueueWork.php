<?php

namespace Michalsn\CodeIgniterQueue\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Exception;
use Michalsn\CodeIgniterQueue\Config\Queue as QueueConfig;
use Michalsn\CodeIgniterQueue\Entities\QueueJob;
use Throwable;

class QueueWork extends BaseCommand
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
    protected $name = 'queue:work';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Process jobs from a given queue.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:work <queueName> [options]';

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
        '-sleep'            => 'Wait time between the next check for available job when the queue is empty. Default value: 10 (seconds).',
        '-rest'             => 'Rest time between the jobs in the queue. Default value: 0 (seconds)',
        '-max-jobs'         => 'The maximum number of jobs to handle before worker should exit. Disabled by default.',
        '-max-time'         => 'The maximum number of seconds worker should run. Disabled by default.',
        '--stop-when-empty' => 'Stop when the queue is empty.',
    ];

    /**
     * Actually execute a command.
     *
     * @throws Exception
     */
    public function run(array $params)
    {
        /* @var QueueConfig $config */
        $config        = config('Queue');
        $stopWhenEmpty = false;
        $waiting       = false;

        // Read params
        if (! $queue = array_shift($params)) {
            CLI::error('The queueName is not specified.');

            return EXIT_ERROR;
        }

        // Read options
        $sleep     = $params['sleep'] ?? CLI::getOption('sleep') ?? 10;
        $rest      = $params['rest'] ?? CLI::getOption('rest') ?? 0;
        $maxJobs   = $params['max-jobs'] ?? CLI::getOption('max-jobs') ?? 0;
        $maxTime   = $params['max-time'] ?? CLI::getOption('max-time') ?? 0;
        $countJobs = 0;

        if (array_key_exists('stop-when-empty', $params) || CLI::getOption('stop-when-empty')) {
            $stopWhenEmpty = true;
        }

        $startTime = microtime(true);

        CLI::write('Listening for the jobs with the queue: ' . CLI::color($queue, 'light_cyan') . PHP_EOL, 'cyan');

        while (true) {
            $work = service('queue')->pop($queue);

            if ($work === null) {
                if ($stopWhenEmpty) {
                    CLI::write('No job available. Stopping.', 'yellow');

                    return EXIT_SUCCESS;
                }

                if ($waiting === false) {
                    CLI::write('No job in the queue. Waiting...' . PHP_EOL, 'yellow');
                    $waiting = true;
                }

                sleep((int) $sleep);

                if ($this->checkStop($queue, $startTime)) {
                    return EXIT_SUCCESS;
                }

                if ($this->maxTimeCheck($maxTime, $startTime)) {
                    return EXIT_SUCCESS;
                }
            } else {
                $waiting = false;
                $countJobs++;

                CLI::print('Starting a new job: ', 'cyan');
                CLI::print($work->payload['job'], 'light_cyan');
                CLI::print(', with ID: ', 'cyan');
                CLI::print($work->id, 'light_cyan');

                $this->handleWork($work, $config);

                if ($this->checkStop($queue, $startTime)) {
                    return EXIT_SUCCESS;
                }

                if ($this->maxJobsCheck($maxJobs, $countJobs)) {
                    return EXIT_SUCCESS;
                }

                if ($this->maxTimeCheck($maxTime, $startTime)) {
                    return EXIT_SUCCESS;
                }

                if ($rest > 0) {
                    sleep((int) $rest);
                }
            }
        }
    }

    private function handleWork(QueueJob $work, QueueConfig $config): void
    {
        timer()->start('work');
        $payload = $work->payload;

        try {
            $class = $config->resolveJobClass($payload['job']);
            $job   = new $class($payload['data']);
            $job->process();

            // Mark as done
            service('queue')->done($work, $config->keepDoneJobs);

            CLI::write('The processing of this job was successful', 'green');

        } catch (Throwable $err) {
            if (isset($job) && ++$work->attempts < $job->getRetries()) {
                // Schedule for later
                service('queue')->later($work, $job->getRetryAfter());
            } else {
                // Mark as failed
                service('queue')->failed($work, $err, $config->keepFailedJobs);
            }
            CLI::write('The processing of this job failed', 'red');
        } finally {
            timer()->stop('work');
            CLI::write(sprintf('It took: %s sec', timer()->getElapsedTime('work')) . PHP_EOL, 'cyan');
        }
    }

    private function maxJobsCheck(int $maxJobs, int $countJobs): bool
    {
        if ($maxJobs > 0 && $countJobs >= $maxJobs) {
            CLI::write(sprintf('The maximum number of jobs (%s) has been reached. Stopping.', $maxJobs), 'yellow');

            return true;
        }

        return false;
    }

    private function maxTimeCheck(int $maxTime, float $startTime): bool
    {
        if ($maxTime > 0 && microtime(true) - $startTime >= $maxTime) {
            CLI::write(sprintf('The maximum time (%s sec) for worker to run has been reached. Stopping.', $maxTime), 'yellow');

            return true;
        }

        return false;
    }

    private function checkStop(string $queue, float $startTime): bool
    {
        $time = cache()->get(sprintf('queue-%s-stop', $queue));

        if ($time === null) {
            return false;
        }

        if ($startTime < (float) $time) {
            CLI::write('This worker has been scheduled to end. Stopping.', 'yellow');

            return true;
        }

        return false;
    }
}
