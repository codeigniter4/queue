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

use CodeIgniter\Queue\Payloads\ChainBuilder;
use CodeIgniter\Queue\Payloads\ChainElement;
use CodeIgniter\Queue\Payloads\Payload;
use CodeIgniter\Queue\Payloads\PayloadMetadata;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ChainElementTest extends TestCase
{
    private Payload $payload;
    private ChainBuilder $chainBuilder;
    private ChainElement $chainElement;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a payload object
        $this->payload = new Payload('job', ['key' => 'value']);
        $this->payload->setQueue('queue');

        // Create a mock ChainBuilder
        $this->chainBuilder = $this->createMock(ChainBuilder::class);

        // Create the ChainElement to test
        $this->chainElement = new ChainElement($this->payload, $this->chainBuilder);
    }

    public function testSetPriority(): void
    {
        $result = $this->chainElement->setPriority('high');

        $this->assertInstanceOf(ChainElement::class, $result);
        $this->assertSame('high', $this->payload->getPriority());
    }

    public function testSetDelay(): void
    {
        $result = $this->chainElement->setDelay(60);

        $this->assertInstanceOf(ChainElement::class, $result);
        $this->assertSame(60, $this->payload->getDelay());
    }

    public function testPush(): void
    {
        $nextPayload = new Payload('nextJob', ['nextKey' => 'nextValue']);
        $nextElement = new ChainElement($nextPayload, $this->chainBuilder);

        /** @phpstan-ignore-next-line */
        $this->chainBuilder->expects($this->once())
            ->method('push')
            ->with('queue2', 'job2', ['data' => 'value2'])
            ->willReturn($nextElement);

        $result = $this->chainElement->push('queue2', 'job2', ['data' => 'value2']);

        $this->assertInstanceOf(ChainElement::class, $result);
        $this->assertSame($nextElement, $result);
    }

    public function testMultipleMethodChaining(): void
    {
        $chainBuilder = $this->createMock(ChainBuilder::class);
        $chainBuilder->method('push')->willReturnSelf();

        $payload = new Payload('job', ['key' => 'value']);

        $chainElement = new ChainElement($payload, $chainBuilder);

        $chainElement
            ->setPriority('medium')
            ->setDelay(30);

        $this->assertSame('medium', $payload->getPriority());
        $this->assertSame(30, $payload->getDelay());
    }

    public function testCorrectMetadataModification(): void
    {
        $metadata = new PayloadMetadata();
        $payload  = new Payload('job', ['key' => 'value'], $metadata);

        $chainElement = new ChainElement($payload, $this->chainBuilder);

        $chainElement->setPriority('low');
        $chainElement->setDelay(120);

        $this->assertSame('low', $payload->getMetadata()->get('priority'));
        $this->assertSame(120, $payload->getMetadata()->get('delay'));
    }
}
