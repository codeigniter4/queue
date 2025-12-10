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

use ArrayIterator;
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadCollection;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class PayloadCollectionTest extends TestCase
{
    private PayloadCollection $collection;
    private Payload $payload1;
    private Payload $payload2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sample payloads
        $this->payload1 = new Payload('job1', ['key1' => 'value1']);
        $this->payload1->setQueue('queue1');

        $this->payload2 = new Payload('job2', ['key2' => 'value2']);
        $this->payload2->setQueue('queue2');

        // Create an empty collection
        $this->collection = new PayloadCollection();
    }

    public function testEmptyCollectionCount(): void
    {
        $this->assertCount(0, $this->collection);
    }

    public function testAddPayload(): void
    {
        $result = $this->collection->add($this->payload1);

        $this->assertInstanceOf(PayloadCollection::class, $result);
        $this->assertCount(1, $this->collection);
    }

    public function testAddMultiplePayloads(): void
    {
        $this->collection->add($this->payload1);
        $this->collection->add($this->payload2);

        $this->assertCount(2, $this->collection);
    }

    public function testShiftPayload(): void
    {
        $this->collection->add($this->payload1);
        $this->collection->add($this->payload2);

        $first = $this->collection->shift();

        $this->assertSame($this->payload1, $first);
        $this->assertCount(1, $this->collection);
    }

    public function testShiftFromEmptyCollection(): void
    {
        $result = $this->collection->shift();

        $this->assertNull($result);
    }

    public function testGetIterator(): void
    {
        $this->collection->add($this->payload1);
        $this->collection->add($this->payload2);

        $iterator = $this->collection->getIterator();

        $this->assertInstanceOf(ArrayIterator::class, $iterator);
        $this->assertCount(2, $iterator);
    }

    public function testToArray(): void
    {
        $this->collection->add($this->payload1);
        $this->collection->add($this->payload2);

        $array = $this->collection->toArray();

        $this->assertCount(2, $array);

        // Check array structure
        $this->assertArrayHasKey('job', $array[0]);
        $this->assertArrayHasKey('data', $array[0]);
        $this->assertArrayHasKey('metadata', $array[0]);

        $this->assertSame('job1', $array[0]['job']);
        $this->assertSame(['key1' => 'value1'], $array[0]['data']);
    }

    public function testJsonSerialize(): void
    {
        $this->collection->add($this->payload1);
        $this->collection->add($this->payload2);

        $json    = json_encode($this->collection);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame('job1', $decoded[0]['job']);
        $this->assertSame('job2', $decoded[1]['job']);
    }

    public function testFromArray(): void
    {
        $arrayData = [
            [
                'job'      => 'job1',
                'data'     => ['key1' => 'value1'],
                'metadata' => ['queue' => 'queue1'],
            ],
            [
                'job'      => 'job2',
                'data'     => ['key2' => 'value2'],
                'metadata' => ['queue' => 'queue2'],
            ],
        ];

        $collection = PayloadCollection::fromArray($arrayData);

        $this->assertInstanceOf(PayloadCollection::class, $collection);
        $this->assertCount(2, $collection);

        $first = $collection->shift();
        $this->assertInstanceOf(Payload::class, $first);
        $this->assertSame('job1', $first->getJob());
        $this->assertSame(['key1' => 'value1'], $first->getData());
    }

    public function testInvalidDataInFromArray(): void
    {
        $arrayData = [
            ['invalid' => 'data'], // Missing job and data
            [
                'job'  => 'job2',
                'data' => ['key2' => 'value2'],
            ],
        ];

        $collection = PayloadCollection::fromArray($arrayData);

        // Should only have created one valid payload
        $this->assertCount(1, $collection);
    }

    public function testIteration(): void
    {
        $this->collection->add($this->payload1);
        $this->collection->add($this->payload2);

        $count = 0;
        $jobs  = [];

        foreach ($this->collection as $payload) {
            $count++;
            $jobs[] = $payload->getJob();
        }

        $this->assertSame(2, $count);
        $this->assertSame(['job1', 'job2'], $jobs);
    }
}
