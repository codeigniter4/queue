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

namespace Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Queue\Entities\QueueJob;
use CodeIgniter\Test\ReflectionHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\Database\Seeds\TestDatabaseQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class PushAndPopWithDelayTest extends TestCase
{
    use ReflectionHelper;

    protected $seed = TestDatabaseQueueSeeder::class;
    private QueueConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);
    }

    #[DataProvider('providePushAndPopWithDelay')]
    public function testPushAndPopWithDelay(string $name, string $class): void
    {
        Time::setTestNow('2023-12-29 14:15:16');

        $handler = new $class($this->config);
        $result  = $handler->setDelay(MINUTE)->push('queue-delay', 'success', ['key1' => 'value1']);

        $this->assertNotNull($result);

        $result = $handler->push('queue-delay', 'success', ['key2' => 'value2']);

        $this->assertNotNull($result);

        if ($name === 'database') {
            $this->seeInDatabase('queue_jobs', [
                'queue'        => 'queue-delay',
                'payload'      => json_encode(['job' => 'success', 'data' => ['key1' => 'value1'], 'metadata' => []]),
                'available_at' => 1703859376,
            ]);

            $this->seeInDatabase('queue_jobs', [
                'queue'        => 'queue-delay',
                'payload'      => json_encode(['job' => 'success', 'data' => ['key2' => 'value2'], 'metadata' => []]),
                'available_at' => 1703859316,
            ]);
        }

        $result = $handler->pop('queue-delay', ['default']);
        $this->assertInstanceOf(QueueJob::class, $result);
        $payload = ['job' => 'success', 'data' => ['key2' => 'value2'], 'metadata' => []];
        $this->assertSame($payload, $result->payload);

        $result = $handler->pop('queue-delay', ['default']);
        $this->assertNull($result);

        // add 1 minute
        Time::setTestNow('2023-12-29 14:16:16');

        $result = $handler->pop('queue-delay', ['default']);
        $this->assertInstanceOf(QueueJob::class, $result);
        $payload = ['job' => 'success', 'data' => ['key1' => 'value1'], 'metadata' => []];
        $this->assertSame($payload, $result->payload);
    }

    public static function providePushAndPopWithDelay(): iterable
    {
        return [
            [
                'database',                                   // name
                'CodeIgniter\Queue\Handlers\DatabaseHandler', // class
            ],
            [
                'redis',
                'CodeIgniter\Queue\Handlers\RedisHandler',
            ],
            [
                'predis',
                'CodeIgniter\Queue\Handlers\PredisHandler',
            ],
        ];
    }
}
