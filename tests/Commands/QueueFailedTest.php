<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Models\QueueJobFailedModel;
use CodeIgniter\Test\Filters\CITestStreamFilter;
use Exception;
use Tests\Support\CLITestCase;

/**
 * @internal
 */
final class QueueFailedTest extends CLITestCase
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

        $this->assertNotFalse(command('queue:failed'));
        $output = $this->parseOutput(CITestStreamFilter::$buffer);

        CITestStreamFilter::removeOutputFilter();

        $expect = <<<'EOT'
            +----+------------+-------+----------------------------+---------------------+
            | ID | Connection | Queue | Class                      | Failed At           |
            +----+------------+-------+----------------------------+---------------------+
            | 1  | database   | test  | Tests\Support\Jobs\Failure | 2023-12-19 14:15:16 |
            +----+------------+-------+----------------------------+---------------------+
            EOT;

        $this->assertSame($expect, $output);
    }
}
