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
use CodeIgniter\Queue\Models\QueueJobModel;
use CodeIgniter\Test\Filters\CITestStreamFilter;
use Tests\Support\CLITestCase;

/**
 * @internal
 */
final class QueueWorkTest extends CLITestCase
{
    public function testRunWithNoQueueName(): void
    {
        CITestStreamFilter::registration();
        CITestStreamFilter::addErrorFilter();

        $this->assertNotFalse(command('queue:work'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeErrorFilter();

        $this->assertSame('The queueName is not specified.', $output);
    }

    public function testRunWithEmptyQueue(): void
    {
        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:work test --stop-when-empty'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $expect = <<<'EOT'
            Listening for the jobs with the queue: test


            No job available. Stopping.
            EOT;

        $this->assertSame($expect, $output);
    }

    public function testRunWithQueueFailed(): void
    {
        Time::setTestNow('2023-12-19 14:15:16');

        fake(QueueJobModel::class, [
            'connection'   => 'database',
            'queue'        => 'test',
            'payload'      => ['job' => 'failure', 'data' => ['key' => 'value']],
            'priority'     => 'default',
            'status'       => 0,
            'attempts'     => 0,
            'available_at' => 1_702_977_074,
        ]);

        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:work test sleep 1 --stop-when-empty'));
        $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $this->assertSame('Listening for the jobs with the queue: test', $this->getLine(0));
        $this->assertSame('Starting a new job: failure, with ID: 1', $this->getLine(3));
        $this->assertSame('The processing of this job failed', $this->getLine(4));
        $this->assertSame('No job available. Stopping.', $this->getLine(7));
    }

    public function testRunWithQueueSucceed(): void
    {
        Time::setTestNow('2023-12-19 14:15:16');

        fake(QueueJobModel::class, [
            'connection'   => 'database',
            'queue'        => 'test',
            'payload'      => ['job' => 'success', 'data' => ['key' => 'value']],
            'priority'     => 'default',
            'status'       => 0,
            'attempts'     => 0,
            'available_at' => 1_702_977_074,
        ]);

        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();

        $this->assertNotFalse(command('queue:work test sleep 1 --stop-when-empty'));
        $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $this->assertSame('Listening for the jobs with the queue: test', $this->getLine(0));
        $this->assertSame('Starting a new job: success, with ID: 1', $this->getLine(3));
        $this->assertSame('The processing of this job was successful', $this->getLine(4));
        $this->assertSame('No job available. Stopping.', $this->getLine(7));
    }
}
