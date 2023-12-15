<?php

namespace Tests\Support\Jobs;

use Exception;
use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;

class Failure extends BaseJob implements JobInterface
{
    /**
     * @throws Exception
     */
    public function process(): never
    {
        throw new Exception('Failure');
    }
}
