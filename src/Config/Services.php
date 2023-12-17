<?php

namespace CodeIgniter\Queue\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Queue\Config\Queue as QueueConfig;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\Queue;

class Services extends BaseService
{
    public static function queue(?QueueConfig $config = null, $getShared = true): QueueInterface
    {
        if ($getShared) {
            return static::getSharedInstance('queue', $config);
        }

        /** @var QueueConfig|null $config */
        $config ??= config('Queue');

        return (new Queue($config))->init();
    }
}
