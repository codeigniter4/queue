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
use CodeIgniter\Queue\Enums\Status;
use CodeIgniter\Queue\Handlers\RabbitMQHandler;
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Test\ReflectionHelper;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\AMQPTable;
use ReflectionClass;
use RuntimeException;
use Tests\Support\Config\Queue as QueueConfig;
use Tests\Support\TestCase;

/**
 * Unit tests for RabbitMQHandler logic that do not require a live RabbitMQ server.
 *
 * @internal
 */
final class RabbitMQHandlerUnitTest extends TestCase
{
    use ReflectionHelper;

    private QueueConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config(QueueConfig::class);
    }

    public function testGetDelayQueueNameIncludesPriority(): void
    {
        $handler = $this->createHandlerWithMocks();
        $invoker = $this->getPrivateMethodInvoker($handler, 'getDelayQueueName');

        $this->assertSame('queue_emails_high_delay', $invoker('emails', 'high'));
        $this->assertSame('queue_emails_low_delay', $invoker('emails', 'low'));
        $this->assertSame('queue_tasks_default_delay', $invoker('tasks', 'default'));
    }

    public function testPublishDelayedMessageDeclaresPerPriorityQueueAndDeadLetterRouting(): void
    {
        $declaredQueue = null;
        /** @var AMQPTable|null $declaredArgs */
        $declaredArgs = null;
        $bindings     = [];
        $exchanges    = [];

        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())
            ->method('exchange_declare')
            ->willReturnCallback(static function (string $exchange) use (&$exchanges): void {
                $exchanges[] = $exchange;
            });

        $channel->expects($this->once())
            ->method('queue_declare')
            ->willReturnCallback(static function ($queue, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments) use (&$declaredQueue, &$declaredArgs) {
                $declaredQueue = $queue;
                $declaredArgs  = $arguments;

                return [$queue, 0, 0];
            });

        $channel->method('queue_bind')
            ->willReturnCallback(static function (string $queueName, string $exchange, string $routingKey) use (&$bindings): void {
                $bindings[] = [$queueName, $exchange, $routingKey];
            });

        $channel->expects($this->once())
            ->method('basic_publish');

        $handler = $this->createHandlerWithMocks($channel);
        $invoker = $this->getPrivateMethodInvoker($handler, 'publishDelayedMessage');

        $job = new QueueJob([
            'id'       => 1,
            'queue'    => 'emails',
            'payload'  => new Payload('success', ['foo' => 'bar']),
            'priority' => 'high',
            'status'   => Status::PENDING->value,
            'attempts' => 0,
        ]);

        $invoker('emails', $job, 'high', 30);

        $this->assertSame('queue_emails_high_delay', $declaredQueue);
        $this->assertInstanceOf(AMQPTable::class, $declaredArgs);
        $nativeArgs = $declaredArgs->getNativeData();
        $this->assertSame('queue_emails_exchange', $nativeArgs['x-dead-letter-exchange']);
        $this->assertSame('emails.high', $nativeArgs['x-dead-letter-routing-key']);

        // The exchange is declared because later() may run on a worker that never pushed.
        $this->assertSame(['queue_emails_exchange'], $exchanges);

        $delayBind = ['queue_emails_high_delay', 'queue_emails_exchange', 'queue_emails_high_delay'];
        $this->assertCount(1, array_keys($bindings, $delayBind, true), 'The delay queue must be bound exactly once.');
    }

    public function testPublishDelayedMessageBindsDelayQueueOnlyOnceAcrossPublications(): void
    {
        $delayBindCount = 0;

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('queue_declare')
            ->willReturn(['queue_emails_high_delay', 0, 0]);
        $channel->method('queue_bind')
            ->willReturnCallback(static function (string $queueName) use (&$delayBindCount): void {
                if ($queueName === 'queue_emails_high_delay') {
                    $delayBindCount++;
                }
            });
        $channel->expects($this->exactly(2))
            ->method('basic_publish');

        $handler = $this->createHandlerWithMocks($channel);
        $invoker = $this->getPrivateMethodInvoker($handler, 'publishDelayedMessage');

        $job = new QueueJob([
            'id'       => 1,
            'queue'    => 'emails',
            'payload'  => new Payload('success', ['foo' => 'bar']),
            'priority' => 'high',
            'status'   => Status::PENDING->value,
            'attempts' => 0,
        ]);

        $invoker('emails', $job, 'high', 30);
        $invoker('emails', $job, 'high', 60);

        $this->assertSame(1, $delayBindCount);
    }

    public function testLaterPublishesDelayedMessageBeforeRejectingOriginalMessage(): void
    {
        Time::setTestNow('2026-11-10 12:00:00');

        $callOrder = [];

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('queue_declare')
            ->willReturnCallback(static function () use (&$callOrder) {
                $callOrder[] = 'queue_declare';

                return ['queue_emails_high_delay', 0, 0];
            });
        $channel->method('queue_bind')
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'queue_bind';
            });
        $channel->method('basic_publish')
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'basic_publish';
            });
        $channel->expects($this->once())
            ->method('basic_nack')
            ->with('delivery-tag-123', false, false)
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'basic_nack';
            });

        $handler = $this->createHandlerWithMocks($channel);

        $job = new QueueJob([
            'id'       => 1,
            'queue'    => 'emails',
            'payload'  => new Payload('success', ['foo' => 'bar']),
            'priority' => 'high',
            'status'   => Status::RESERVED->value,
            'attempts' => 1,
        ]);
        $job->amqpDeliveryTag = 'delivery-tag-123';

        $result = $handler->later($job, 45);

        $this->assertTrue($result);
        $this->assertSame(Status::PENDING->value, $job->status);
        $this->assertSame(Time::now()->addSeconds(45)->timestamp, $job->available_at->timestamp);

        // Verify order: publishing occurred before basic_nack
        $publishIndex = array_search('basic_publish', $callOrder, true);
        $nackIndex    = array_search('basic_nack', $callOrder, true);
        $this->assertNotFalse($publishIndex);
        $this->assertNotFalse($nackIndex);
        $this->assertLessThan($nackIndex, $publishIndex);
    }

    public function testLaterDoesNotRejectOriginalMessageWhenPublishingDelayedFails(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')
            ->willThrowException(new RuntimeException('Failed to publish delayed replacement'));
        $channel->expects($this->never())
            ->method('basic_nack');

        $handler = $this->createHandlerWithMocks($channel);

        $job = new QueueJob([
            'id'       => 1,
            'queue'    => 'emails',
            'payload'  => new Payload('success', ['foo' => 'bar']),
            'priority' => 'high',
            'status'   => Status::RESERVED->value,
            'attempts' => 1,
        ]);
        $job->amqpDeliveryTag = 'delivery-tag-456';

        $result = $handler->later($job, 60);

        $this->assertFalse($result);
        $this->assertLogContains('error', 'RabbitMQ later error: Failed to publish delayed replacement');
    }

    public function testClearQueuePurgesDelayQueuesForEachPriority(): void
    {
        $purgedQueues = [];

        $disposableChannel = $this->createMock(AMQPChannel::class);
        $disposableChannel->method('queue_purge')
            ->willReturnCallback(static function (string $queue) use (&$purgedQueues): int {
                $purgedQueues[] = $queue;

                return 0;
            });
        $disposableChannel->method('is_open')
            ->willReturn(true);

        $connection = $this->createMock(AbstractConnection::class);
        $connection->method('channel')
            ->willReturn($disposableChannel);

        $config                             = clone $this->config;
        $config->queuePriorities['reports'] = ['high', 'low'];

        $handler = $this->createHandlerWithMocks(null, $connection, $config);
        $invoker = $this->getPrivateMethodInvoker($handler, 'clearQueue');

        $invoker('reports');

        $this->assertContains('queue_reports_high_delay', $purgedQueues);
        $this->assertContains('reports_high', $purgedQueues);
        $this->assertContains('queue_reports_low_delay', $purgedQueues);
        $this->assertContains('reports_low', $purgedQueues);
        // Legacy (pre-priority) delay queue must be purged as well.
        $this->assertContains('queue_reports_delay', $purgedQueues);
    }

    public function testPushResetsPriorityAndDelayInFinallyBlockOnFailure(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')
            ->willThrowException(new RuntimeException('Network failure on push'));

        $handler = $this->createHandlerWithMocks($channel);
        $handler->setPriority('high')->setDelay(30);

        $this->assertSame('high', self::getPrivateProperty($handler, 'priority'));
        $this->assertSame(30, self::getPrivateProperty($handler, 'delay'));

        $result = $handler->push('queue', 'success', ['test' => 'data']);

        $this->assertFalse($result->getStatus());
        $this->assertNull(self::getPrivateProperty($handler, 'priority'));
        $this->assertNull(self::getPrivateProperty($handler, 'delay'));
    }

    private function createHandlerWithMocks(?AMQPChannel $channel = null, ?AbstractConnection $connection = null, ?QueueConfig $config = null): RabbitMQHandler
    {
        $ref     = new ReflectionClass(RabbitMQHandler::class);
        $handler = $ref->newInstanceWithoutConstructor();

        $config ??= $this->config;
        $this->setPrivateProperty($handler, 'config', $config);

        $channel ??= $this->createMock(AMQPChannel::class);
        $connection ??= $this->createMock(AbstractConnection::class);

        $this->setPrivateProperty($handler, 'channel', $channel);
        $this->setPrivateProperty($handler, 'connection', $connection);
        $this->setPrivateProperty($handler, 'declaredLogicalQueues', []);
        $this->setPrivateProperty($handler, 'declaredQueues', []);
        $this->setPrivateProperty($handler, 'declaredExchanges', []);

        return $handler;
    }
}
