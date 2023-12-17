<?php

namespace Tests\Support\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;

class Success extends BaseJob implements JobInterface
{
    protected int $retryAfter = 6;
    protected int $retries    = 3;

    public function process(): bool
    {
        return true;
    }
}
