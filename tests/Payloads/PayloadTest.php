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

use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadCollection;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class PayloadTest extends TestCase
{
    private Payload $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payload = new Payload('job', ['key' => 'value']);
    }

    public function testConstructor(): void
    {
        $this->assertSame('job', $this->payload->getJob());
        $this->assertSame(['key' => 'value'], $this->payload->getData());
    }

    public function testConstructorWithMetadata(): void
    {
        $metadata = new PayloadMetadata();
        $metadata->set('priority', 'high');

        $payload = new Payload('job', ['key' => 'value'], $metadata);

        $this->assertSame('high', $payload->getMetadata()->get('priority'));
    }

    public function testGetJob(): void
    {
        $this->assertSame('job', $this->payload->getJob());
    }

    public function testGetData(): void
    {
        $this->assertSame(['key' => 'value'], $this->payload->getData());
    }

    public function testGetMetadata(): void
    {
        $metadata = $this->payload->getMetadata();

        $this->assertInstanceOf(PayloadMetadata::class, $metadata);
    }

    public function testSetMetadata(): void
    {
        $metadata = new PayloadMetadata();
        $metadata->set('priority', 'high');

        $result = $this->payload->setMetadata($metadata);

        $this->assertInstanceOf(Payload::class, $result);
        $this->assertSame('high', $this->payload->getMetadata()->get('priority'));
    }

    public function testSetQueue(): void
    {
        $result = $this->payload->setQueue('queue');

        $this->assertInstanceOf(Payload::class, $result);
        $this->assertSame('queue', $this->payload->getQueue());
    }

    public function testSetQueueWithInvalidFormat(): void
    {
        $this->expectException(QueueException::class);

        $this->payload->setQueue('invalid queue name!');
    }

    public function testSetQueueWithTooLongName(): void
    {
        $this->expectException(QueueException::class);

        $this->payload->setQueue(str_repeat('a', 65)); // 65 characters, too long
    }

    public function testGetQueue(): void
    {
        $this->payload->setQueue('queue');

        $this->assertSame('queue', $this->payload->getQueue());
    }

    public function testSetPriority(): void
    {
        $result = $this->payload->setPriority('high');

        $this->assertInstanceOf(Payload::class, $result);
        $this->assertSame('high', $this->payload->getPriority());
    }

    public function testSetPriorityWithInvalidFormat(): void
    {
        $this->expectException(QueueException::class);

        $this->payload->setPriority('invalid priority!');
    }

    public function testSetPriorityWithTooLongName(): void
    {
        $this->expectException(QueueException::class);

        $this->payload->setPriority(str_repeat('a', 65)); // 65 characters, too long
    }

    public function testGetPriority(): void
    {
        $this->payload->setPriority('high');

        $this->assertSame('high', $this->payload->getPriority());
    }

    public function testSetDelay(): void
    {
        $result = $this->payload->setDelay(60);

        $this->assertInstanceOf(Payload::class, $result);
        $this->assertSame(60, $this->payload->getDelay());
    }

    public function testSetDelayWithNegativeValue(): void
    {
        $this->expectException(QueueException::class);

        $this->payload->setDelay(-1);
    }

    public function testGetDelay(): void
    {
        $this->payload->setDelay(60);

        $this->assertSame(60, $this->payload->getDelay());
    }

    public function testSetChainedJobs(): void
    {
        $payloads = new PayloadCollection();
        $payloads->add(new Payload('nextJob', ['nextKey' => 'nextValue']));

        $result = $this->payload->setChainedJobs($payloads);

        $this->assertInstanceOf(Payload::class, $result);
        $this->assertTrue($this->payload->hasChainedJobs());
    }

    public function testGetChainedJobs(): void
    {
        $payloads = new PayloadCollection();
        $payloads->add(new Payload('nextJob', ['nextKey' => 'nextValue']));

        $this->payload->setChainedJobs($payloads);
        $chainedJobs = $this->payload->getChainedJobs();

        $this->assertInstanceOf(PayloadCollection::class, $chainedJobs);
        $this->assertCount(1, $chainedJobs);
    }

    public function testHasChainedJobs(): void
    {
        $this->assertFalse($this->payload->hasChainedJobs());

        $payloads = new PayloadCollection();
        $payloads->add(new Payload('nextJob', ['nextKey' => 'nextValue']));

        $this->payload->setChainedJobs($payloads);

        $this->assertTrue($this->payload->hasChainedJobs());
    }

    public function testJsonSerialize(): void
    {
        $this->payload->setQueue('queue');
        $this->payload->setPriority('high');

        $json    = json_encode($this->payload);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('job', $decoded['job']);
        $this->assertSame(['key' => 'value'], $decoded['data']);
        $this->assertIsArray($decoded['metadata']);
        $this->assertSame('queue', $decoded['metadata']['queue']);
        $this->assertSame('high', $decoded['metadata']['priority']);
    }

    public function testJsonSerializeWithChainedJobs(): void
    {
        $this->payload->setQueue('queue');

        $nextPayload = new Payload('nextJob', ['nextKey' => 'nextValue']);
        $nextPayload->setQueue('queue');

        $payloads = new PayloadCollection();
        $payloads->add($nextPayload);

        $this->payload->setChainedJobs($payloads);

        $json    = json_encode($this->payload);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('metadata', $decoded);
        $this->assertArrayHasKey('chainedJobs', $decoded['metadata']);
        $this->assertIsArray($decoded['metadata']['chainedJobs']);
        $this->assertCount(1, $decoded['metadata']['chainedJobs']);
        $this->assertSame('nextJob', $decoded['metadata']['chainedJobs'][0]['job']);
        $this->assertSame(['nextKey' => 'nextValue'], $decoded['metadata']['chainedJobs'][0]['data']);
    }

    public function testFromArray(): void
    {
        $data = [
            'job'      => 'job',
            'data'     => ['key' => 'value'],
            'metadata' => [
                'queue'    => 'queue',
                'priority' => 'high',
            ],
        ];

        $payload = Payload::fromArray($data);

        $this->assertSame('job', $payload->getJob());
        $this->assertSame(['key' => 'value'], $payload->getData());
        $this->assertSame('queue', $payload->getQueue());
        $this->assertSame('high', $payload->getPriority());
    }

    public function testFromArrayWithChainedJobs(): void
    {
        $data = [
            'job'      => 'job',
            'data'     => ['key' => 'value'],
            'metadata' => [
                'queue'       => 'queue',
                'chainedJobs' => [
                    [
                        'job'      => 'nextJob',
                        'data'     => ['nextKey' => 'nextValue'],
                        'metadata' => ['queue' => 'nextQueue'],
                    ],
                ],
            ],
        ];

        $payload = Payload::fromArray($data);

        $this->assertTrue($payload->hasChainedJobs());
        $chainedJobs = $payload->getChainedJobs();
        $this->assertCount(1, $chainedJobs);
        $this->assertInstanceOf(PayloadCollection::class, $chainedJobs);

        $nextJob = $chainedJobs->shift();
        $this->assertInstanceOf(Payload::class, $nextJob);
        $this->assertSame('nextJob', $nextJob->getJob());
        $this->assertSame(['nextKey' => 'nextValue'], $nextJob->getData());
        $this->assertSame('nextQueue', $nextJob->getQueue());
    }

    public function testMultipleValidations(): void
    {
        $payload = new Payload('job', ['key' => 'value']);

        // Test that all validations pass
        $payload->setQueue('valid-queue');
        $payload->setPriority('valid-priority');
        $payload->setDelay(30);

        $this->assertSame('valid-queue', $payload->getQueue());
        $this->assertSame('valid-priority', $payload->getPriority());
        $this->assertSame(30, $payload->getDelay());
    }
}
