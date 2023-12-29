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

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Models\QueueJobFailedModel;
use CodeIgniter\Test\Filters\CITestStreamFilter;
use Exception;
use Tests\Support\CLITestCase;

/**
 * @internal
 */
final class QueueFlushTest extends CLITestCase
{
    /**
     * @throws Exception
     */
    public function testRun(): void
    {
        Time::setTestNow('2023-12-19 14:15:16');

        fake(QueueJobFailedModel::class, [
            'connection' => 'database',
            'queue'      => 'test',
            'payload'    => ['job' => 'failure', 'data' => ['key' => 'value']],
            'priority'   => 'default',
            'exception'  => 'Exception: Test error',
        ]);

        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:flush'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $this->assertSame('All failed jobs has been removed from the queue ', $output);
    }

    public function testRunWithQueue(): void
    {
        Time::setTestNow('2023-12-19 14:15:16');

        fake(QueueJobFailedModel::class, [
            'connection' => 'database',
            'queue'      => 'test',
            'payload'    => ['job' => 'failure', 'data' => ['key' => 'value']],
            'priority'   => 'default',
            'exception'  => 'Exception: Test error',
        ]);

        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:flush -queue default'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $this->assertSame('All failed jobs has been removed from the queue default', $output);
    }

    public function testRunWithQueueAndHour(): void
    {
        Time::setTestNow('2023-12-19 14:15:16');

        fake(QueueJobFailedModel::class, [
            'connection' => 'database',
            'queue'      => 'test',
            'payload'    => ['job' => 'failure', 'data' => ['key' => 'value']],
            'priority'   => 'default',
            'exception'  => 'Exception: Test error',
        ]);

        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:flush -queue default -hours 2'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $this->assertSame('All failed jobs older than 2 hours has been removed from the queue default', $output);
    }
}
