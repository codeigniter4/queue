<?php

namespace Tests\Support\Jobs;

use Exception;
use Michalsn\CodeIgniterQueue\BaseJob;
use Michalsn\CodeIgniterQueue\Interfaces\JobInterface;

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
