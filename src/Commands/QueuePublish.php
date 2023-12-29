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
use CodeIgniter\Publisher\Publisher;
use Throwable;

class QueuePublish extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'queue:publish';
    protected $description = 'Publish Queue config file into the current application.';

    public function run(array $params): void
    {
        $source = service('autoloader')->getNamespace('CodeIgniter\\Queue')[0];

        $publisher = new Publisher($source, APPPATH);

        try {
            $publisher->addPaths([
                'Config/Queue.php',
            ])->merge(false);
        } catch (Throwable $e) {
            $this->showError($e);

            return;
        }

        foreach ($publisher->getPublished() as $file) {
            $contents = file_get_contents($file);
            $contents = str_replace('namespace CodeIgniter\\Queue\\Config', 'namespace Config', $contents);
            $contents = str_replace('use CodeIgniter\\Config\\BaseConfig', 'use CodeIgniter\\Queue\\Config\\Queue as BaseQueue', $contents);
            $contents = str_replace('class Queue extends BaseConfig', 'class Queue extends BaseQueue', $contents);
            $method   = <<<'EOT'

                    public function __construct()
                    {
                        parent::__construct();

                        if (ENVIRONMENT === 'testing') {
                            $this->database['dbGroup'] = config('database')->defaultGroup;
                        }
                    }

                    /**
                     * Resolve job class name.
                     *
                     * @return class-string<JobInterface>
                     */
                    public function resolveJobClass(string $name): string
                    {
                        if (! isset($this->jobHandlers[$name])) {
                            throw QueueException::forIncorrectJobHandler();
                        }

                        return $this->jobHandlers[$name];
                    }

                    /**
                     * Stringify queue priorities.
                     */
                    public function getQueuePriorities(string $name): ?string
                    {
                        if (! isset($this->queuePriorities[$name])) {
                            return null;
                        }

                        return implode(',', $this->queuePriorities[$name]);
                    }
                EOT;
            $contents = str_replace($method, '', $contents);
            file_put_contents($file, $contents);
        }

        CLI::write(CLI::color('  Published! ', 'green') . 'You can customize the configuration by editing the "app/Config/Queue.php" file.');
    }
}
