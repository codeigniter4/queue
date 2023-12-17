<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Handlers\DatabaseHandler;
use CodeIgniter\Queue\Queue;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\Database\Seeds\TestDatabaseQueueSeeder;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class QueueTest extends TestCase
{
    protected $seed = TestDatabaseQueueSeeder::class;
    private QueueConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);
    }

    public function testQueue(): void
    {
        $queue = new Queue($this->config);
        $this->assertInstanceOf(Queue::class, $queue);
    }

    public function testQueueException(): void
    {
        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('This queue handler is incorrect.');

        $this->config->defaultHandler = 'not-exists';

        $queue = new Queue($this->config);
        $this->assertInstanceOf(Queue::class, $queue);
    }

    public function testQueueInit(): void
    {
        $queue = new Queue($this->config);
        $this->assertInstanceOf(DatabaseHandler::class, $queue->init());
    }
}
