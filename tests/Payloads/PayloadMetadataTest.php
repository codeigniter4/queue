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

use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadCollection;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class PayloadMetadataTest extends TestCase
{
    private PayloadMetadata $metadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metadata = new PayloadMetadata();
    }

    public function testEmptyMetadata(): void
    {
        $this->assertSame([], $this->metadata->toArray());
    }

    public function testSetAndGetGenericValue(): void
    {
        $this->metadata->set('key', 'value');

        $this->assertSame('value', $this->metadata->get('key'));
    }

    public function testGetWithDefault(): void
    {
        $this->assertSame('default', $this->metadata->get('nonexistent', 'default'));
    }

    public function testHasKey(): void
    {
        $this->metadata->set('key', 'value');

        $this->assertTrue($this->metadata->has('key'));
        $this->assertFalse($this->metadata->has('nonexistent'));
    }

    public function testRemoveKey(): void
    {
        $this->metadata->set('key', 'value');
        $this->metadata->remove('key');

        $this->assertFalse($this->metadata->has('key'));
    }

    public function testSetAndGetChainedJobs(): void
    {
        $payload1 = new Payload('job1', ['key1' => 'value1']);
        $payload2 = new Payload('job2', ['key2' => 'value2']);

        $payloads = new PayloadCollection();
        $payloads->add($payload1);
        $payloads->add($payload2);

        $this->metadata->setChainedJobs($payloads);

        $result = $this->metadata->getChainedJobs();

        $this->assertInstanceOf(PayloadCollection::class, $result);
        $this->assertCount(2, $result);
    }

    public function testSetChainedJobsToNull(): void
    {
        $payload  = new Payload('job', ['key' => 'value']);
        $payloads = new PayloadCollection();
        $payloads->add($payload);

        $this->metadata->setChainedJobs($payloads);

        // Then set to null
        $this->metadata->setChainedJobs(null);

        $this->assertNull($this->metadata->getChainedJobs());
        $this->assertFalse($this->metadata->hasChainedJobs());
    }

    public function testHasChainedJobs(): void
    {
        $this->assertFalse($this->metadata->hasChainedJobs());

        $payload  = new Payload('job', ['key' => 'value']);
        $payloads = new PayloadCollection();
        $payloads->add($payload);

        $this->metadata->setChainedJobs($payloads);

        $this->assertTrue($this->metadata->hasChainedJobs());
    }

    public function testHasChainedJobsWithEmptyCollection(): void
    {
        $emptyCollection = new PayloadCollection();
        $this->metadata->setChainedJobs($emptyCollection);

        $this->assertFalse($this->metadata->hasChainedJobs());
    }

    public function testJsonSerialize(): void
    {
        $this->metadata->set('queue', 'default');
        $this->metadata->set('priority', 'high');

        $json    = json_encode($this->metadata);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('queue', $decoded);
        $this->assertArrayHasKey('priority', $decoded);
        $this->assertSame('default', $decoded['queue']);
        $this->assertSame('high', $decoded['priority']);
    }

    public function testJsonSerializeWithChainedJobs(): void
    {
        $payload = new Payload('job', ['key' => 'value']);
        $payload->setQueue('queue');

        $payloads = new PayloadCollection();
        $payloads->add($payload);

        $this->metadata->setChainedJobs($payloads);

        $json    = json_encode($this->metadata);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('chainedJobs', $decoded);
        $this->assertIsArray($decoded['chainedJobs']);
        $this->assertCount(1, $decoded['chainedJobs']);
        $this->assertSame('job', $decoded['chainedJobs'][0]['job']);
    }

    public function testFromArray(): void
    {
        $data = [
            'queue'    => 'default',
            'priority' => 'high',
            'delay'    => 60,
        ];

        $metadata = PayloadMetadata::fromArray($data);

        $this->assertSame('default', $metadata->get('queue'));
        $this->assertSame('high', $metadata->get('priority'));
        $this->assertSame(60, $metadata->get('delay'));
    }

    public function testFromArrayWithChainedJobs(): void
    {
        $data = [
            'queue'       => 'default',
            'chainedJobs' => [
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
            ],
        ];

        $metadata = PayloadMetadata::fromArray($data);

        $this->assertSame('default', $metadata->get('queue'));
        $this->assertTrue($metadata->hasChainedJobs());

        $chainedJobs = $metadata->getChainedJobs();
        $this->assertInstanceOf(PayloadCollection::class, $chainedJobs);
        $this->assertCount(2, $chainedJobs);

        $job1 = $chainedJobs->shift();
        $this->assertInstanceOf(Payload::class, $job1);
        $this->assertSame('job1', $job1->getJob());
        $this->assertSame(['key1' => 'value1'], $job1->getData());
        $this->assertSame('queue1', $job1->getQueue());
    }

    public function testFromArrayWithInvalidChainedJobs(): void
    {
        $data = [
            'chainedJobs' => [
                ['invalid' => 'data'], // Missing job and data
                [
                    'job'  => 'job2',
                    'data' => ['key2' => 'value2'],
                ],
            ],
        ];

        $metadata = PayloadMetadata::fromArray($data);

        $this->assertTrue($metadata->hasChainedJobs());
        $this->assertSame(1, $metadata->getChainedJobs()->count());
    }

    public function testToArray(): void
    {
        $this->metadata->set('queue', 'default');
        $this->metadata->set('priority', 'high');

        $array = $this->metadata->toArray();

        $this->assertArrayHasKey('queue', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertSame('default', $array['queue']);
        $this->assertSame('high', $array['priority']);
    }
}
