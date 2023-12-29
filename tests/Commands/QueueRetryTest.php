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

namespace Tests\Commands;

use CodeIgniter\Queue\Models\QueueJobFailedModel;
use CodeIgniter\Test\Filters\CITestStreamFilter;
use Tests\Support\CLITestCase;

/**
 * @internal
 */
final class QueueRetryTest extends CLITestCase
{
    public function testRunWithNoQueueName(): void
    {
        CITestStreamFilter::registration();
        CITestStreamFilter::addErrorFilter();

        $this->assertNotFalse(command('queue:retry'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeErrorFilter();

        $this->assertSame('The ID of the failed job is not specified.', $output);
    }

    public function testRunFailed(): void
    {
        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:retry all -queue test'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $this->assertSame('No failed jobs has been restored to the queue test', $output);
    }

    public function testRun(): void
    {
        fake(QueueJobFailedModel::class, [
            'connection' => 'database',
            'queue'      => 'test',
            'payload'    => ['job' => 'failure', 'data' => ['key' => 'value']],
            'priority'   => 'default',
            'exception'  => 'Exception: Test error',
        ]);

        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:retry 1'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $this->assertSame('1 failed job(s) has been restored to the queue ', $output);
    }
}
