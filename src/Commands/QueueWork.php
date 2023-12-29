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
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Entities\QueueJob;
use Exception;
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
        '-sleep'            => 'Wait time between the next check for available job when the queue is empty. Default value: 10 (seconds).',
        '-rest'             => 'Rest time between the jobs in the queue. Default value: 0 (seconds)',
        '-max-jobs'         => 'The maximum number of jobs to handle before worker should exit. Disabled by default.',
        '-max-time'         => 'The maximum number of seconds worker should run. Disabled by default.',
        '-memory'           => 'The maximum memory in MB that worker can take. Default value: 128',
        '-priority'         => 'The priority for the jobs from the queue (comma separated). If not provided explicit, will follow the priorities defined in the config via $queuePriorities for the given queue. Disabled by default.',
        '-tries'            => 'The number of attempts after which the job will be considered as failed. Overrides settings from the Job class. Disabled by default.',
        '-retry-after'      => 'The number of seconds after which the job is to be restarted in case of failure. Overrides settings from the Job class. Disabled by default.',
        '--stop-when-empty' => 'Stop when the queue is empty.',
    ];

    /**
     * Actually execute a command.
     *
     * @throws Exception
     */
    public function run(array $params)
    {
        set_time_limit(0);

        /** @var QueueConfig $config */
        $config        = config('Queue');
        $stopWhenEmpty = false;
        $waiting       = false;

        // Read queue name from params
        $queue = array_shift($params);
        if ($queue === null) {
            CLI::error('The queueName is not specified.');

            return EXIT_ERROR;
        }

        // Read options
        [
            $error,
            $sleep,
            $rest,
            $maxJobs,
            $maxTime,
            $memory,
            $priority,
            $tries,
            $retryAfter
        ] = $this->readOptions($params, $config, $queue);

        if ($error !== null) {
            CLI::write($error, 'red');

            return EXIT_ERROR;
        }

        $countJobs = 0;

        if (array_key_exists('stop-when-empty', $params) || CLI::getOption('stop-when-empty')) {
            $stopWhenEmpty = true;
        }

        $startTime = microtime(true);

        CLI::write('Listening for the jobs with the queue: ' . CLI::color($queue, 'light_cyan'), 'cyan');

        if ($priority !== 'default') {
            CLI::write('Jobs will be consumed according to priority: ' . CLI::color($priority, 'light_cyan'), 'cyan');
        }

        CLI::write(PHP_EOL);

        $priority = array_map('trim', explode(',', (string) $priority));

        while (true) {
            $work = service('queue')->pop($queue, $priority);

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

                if ($this->checkMemory($memory)) {
                    return EXIT_SUCCESS;
                }

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
                CLI::print((string) $work->id, 'light_cyan');

                $this->handleWork($work, $config, $tries, $retryAfter);

                if ($this->checkMemory($memory)) {
                    return EXIT_SUCCESS;
                }

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

    private function readOptions(array $params, QueueConfig $config, string $queue): array
    {
        $options = [
            'error'      => null,
            'sleep'      => $params['sleep'] ?? CLI::getOption('sleep') ?? 10,
            'rest'       => $params['rest'] ?? CLI::getOption('rest') ?? 0,
            'maxJobs'    => $params['max-jobs'] ?? CLI::getOption('max-jobs') ?? 0,
            'maxTime'    => $params['max-time'] ?? CLI::getOption('max-time') ?? 0,
            'memory'     => $params['memory'] ?? CLI::getOption('memory') ?? 128,
            'priority'   => $params['priority'] ?? CLI::getOption('priority') ?? $config->getQueuePriorities($queue) ?? 'default',
            'tries'      => $params['tries'] ?? CLI::getOption('tries'),
            'retryAfter' => $params['retry-after'] ?? CLI::getOption('retry-after'),
        ];

        // Options that, being defined, cannot be `true`
        $keys = ['sleep', 'rest', 'maxJobs', 'maxTime', 'memory', 'priority', 'tries', 'retryAfter'];

        foreach ($keys as $key) {
            if ($options[$key] === true) {
                $options['error'] = sprintf('Option: "-%s" must have a defined value.', $key);

                return array_values($options);
            }
        }
        // Options that, being defined, have to be `int`
        $keys = array_diff($keys, ['priority']);

        foreach ($keys as $key) {
            if ($options[$key] !== null && ! is_int($options[$key])) {
                $options[$key] = (int) $options[$key];
            }
        }

        return array_values($options);
    }

    private function handleWork(QueueJob $work, QueueConfig $config, ?int $tries, ?int $retryAfter): void
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
            if (isset($job) && ++$work->attempts < ($tries ?? $job->getTries())) {
                // Schedule for later
                service('queue')->later($work, $retryAfter ?? $job->getRetryAfter());
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

    private function checkMemory(int $memory): bool
    {
        if (memory_get_usage(true) > $memory * 1024 * 1024) {
            CLI::write(sprintf('The memory limit of %s MB was reached. Stopping.', $memory), 'yellow');

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
            CLI::write('The termination of this worker has been planned. Stopping.', 'yellow');

            return true;
        }

        return false;
    }
}
