<?php

namespace Michalsn\CodeIgniterQueue\Config;

use CodeIgniter\Config\BaseService;
use Michalsn\CodeIgniterQueue\Config\Queue as QueueConfig;
use Michalsn\CodeIgniterQueue\Queue;

class Services extends BaseService
{
    public static function queue(?QueueConfig $config = null, $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('queue', $config);
        }

        /** @var QueueConfig $config */
        $config ??= config('Queue');

        return (new Queue($config))->init();
    }
}
