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
use CodeIgniter\Queue\Exceptions\QueueException;

abstract class QueueCommand extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Queue';

    /**
     * @throws QueueException
     */
    protected function handleConfig(array $params)
    {
        $configName = $params['config'] ?? CLI::getOption('config');

        if ($configName !== null) {
            $config = config($configName);

            if ($config === null) {
                throw QueueException::forIncorrectConfigFile();
            }

            return $config;
        }

        return config('Queue');
    }

    protected function getConfigHash(QueueConfig $config): string
    {
        return md5($config::class);
    }
}
