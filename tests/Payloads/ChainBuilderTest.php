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

namespace Tests\Payloads;

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Queue\Payloads\ChainBuilder;
use CodeIgniter\Queue\Payloads\ChainElement;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\Database\Seeds\TestDatabaseQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ChainBuilderTest extends TestCase
{
    protected $seed = TestDatabaseQueueSeeder::class;
    private QueueConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);
    }

    public function testChainBuilder(): void
    {
        $handler      = new DatabaseHandler($this->config);
        $chainBuilder = new ChainBuilder($handler);

        $this->assertInstanceOf(ChainBuilder::class, $chainBuilder);
    }

    public function testPush(): void
    {
        $handler      = new DatabaseHandler($this->config);
        $chainBuilder = new ChainBuilder($handler);

        $chainElement = $chainBuilder->push('queue', 'job', ['data' => 'value']);

        $this->assertInstanceOf(ChainElement::class, $chainElement);
    }

    public function testChainWithSingleJob(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new DatabaseHandler($this->config);
        $result  = $handler->chain(static function ($chain): void {
            $chain->push('queue', 'success', ['key' => 'value']);
        });

        $this->assertTrue($result->getStatus());
        $this->seeInDatabase('queue_jobs', [
            'queue'   => 'queue',
            'payload' => json_encode([
                'job'      => 'success',
                'data'     => ['key' => 'value'],
                'metadata' => [
                    'queue' => 'queue',
                ],
            ]),
            'available_at' => 1703859316,
        ]);
    }

    public function testEmptyChain(): void
    {
        $handler = new DatabaseHandler($this->config);
        $result  = $handler->chain(static function ($chain): void {
            // No jobs added
        });

        $this->assertFalse($result->getStatus());
        $this->seeInDatabase('queue_jobs', []);
    }

    public function testMultipleDifferentQueues(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new DatabaseHandler($this->config);
        $result  = $handler->chain(static function ($chain): void {
            $chain
                ->push('queue1', 'success', ['key1' => 'value1'])
                ->push('queue2', 'success', ['key2' => 'value2']);
        });

        $this->assertTrue($result->getStatus());
        $this->seeInDatabase('queue_jobs', [
            'queue'   => 'queue1',
            'payload' => json_encode([
                'job'      => 'success',
                'data'     => ['key1' => 'value1'],
                'metadata' => [
                    'queue'       => 'queue1',
                    'chainedJobs' => [
                        [
                            'job'      => 'success',
                            'data'     => ['key2' => 'value2'],
                            'metadata' => ['queue' => 'queue2'],
                        ],
                    ],
                ],
            ]),
            'available_at' => 1703859316,
        ]);
    }

    public function testChainWithManyJobs(): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new DatabaseHandler($this->config);
        $result  = $handler->chain(static function ($chain): void {
            $chain
                ->push('queue', 'success', ['key1' => 'value1'])
                ->push('queue', 'success', ['key2' => 'value2'])
                ->push('queue', 'success', ['key3' => 'value3']);
        });

        $this->assertTrue($result->getStatus());
        $this->seeInDatabase('queue_jobs', [
            'queue'   => 'queue',
            'payload' => json_encode([
                'job'      => 'success',
                'data'     => ['key1' => 'value1'],
                'metadata' => [
                    'queue'       => 'queue',
                    'chainedJobs' => [
                        [
                            'job'      => 'success',
                            'data'     => ['key2' => 'value2'],
                            'metadata' => ['queue' => 'queue'],
                        ],
                        [
                            'job'      => 'success',
                            'data'     => ['key3' => 'value3'],
                            'metadata' => ['queue' => 'queue'],
                        ],
                    ],
                ],
            ]),
            'available_at' => 1703859316,
        ]);
    }
}
