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

use CodeIgniter\Queue\QueuePushResult;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class QueuePushResultTest extends TestCase
{
    public function testConstructorSuccess(): void
    {
        $result = new QueuePushResult(true, 123456);

        $this->assertTrue($result->getStatus());
        $this->assertSame(123456, $result->getJobId());
        $this->assertNull($result->getError());
    }

    public function testConstructorFailure(): void
    {
        $result = new QueuePushResult(false, null, 'Something went wrong');

        $this->assertFalse($result->getStatus());
        $this->assertNull($result->getJobId());
        $this->assertSame('Something went wrong', $result->getError());
    }

    public function testStaticSuccess(): void
    {
        $result = QueuePushResult::success(999888);

        $this->assertTrue($result->getStatus());
        $this->assertSame(999888, $result->getJobId());
        $this->assertNull($result->getError());
    }

    public function testStaticFailure(): void
    {
        $result = QueuePushResult::failure('Redis error');

        $this->assertFalse($result->getStatus());
        $this->assertNull($result->getJobId());
        $this->assertSame('Redis error', $result->getError());
    }

    public function testStaticFailureWithoutError(): void
    {
        $result = QueuePushResult::failure();

        $this->assertFalse($result->getStatus());
        $this->assertNull($result->getJobId());
        $this->assertNull($result->getError());
    }
}
