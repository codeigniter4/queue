<?php

namespace Michalsn\CodeIgniterQueue\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Publisher\Publisher;
use Throwable;

class QueuePublish extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'queue:publish';
    protected $description = 'Publish QueueJob config file into the current application.';

    /**
     * @return void
     */
    public function run(array $params)
    {
        $source = service('autoloader')->getNamespace('Michalsn\\CodeIgniterQueue')[0];

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
            $contents = str_replace('namespace Michalsn\\CodeIgniterQueue\\Config', 'namespace Config', $contents);
            $contents = str_replace('use CodeIgniter\\Config\\BaseConfig', 'use Michalsn\\CodeIgniterQueue\\Config\\Queue as BaseQueue', $contents);
            $contents = str_replace('class Queue extends BaseConfig', 'class Queue extends BaseQueue', $contents);
            $method   = <<<'EOT'
                    /**
                     * Resolve job class name.
                     */
                    public function resolveJobClass(string \$name): string
                    {
                        if (! isset(\$this->jobHandlers[\$name])) {
                            throw QueueException::forIncorrectJobHandler();
                        }

                        return \$this->jobHandlers[\$name];
                    }
                EOT;
            $contents = str_replace($method, '', $contents);
            file_put_contents($file, $contents);
        }

        CLI::write(CLI::color('  Published! ', 'green') . 'You can customize the configuration by editing the "app/Config/Queue.php" file.');
    }
}
