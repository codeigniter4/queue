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

namespace CodeIgniter\Queue\Traits;

use CodeIgniter\Queue\Exceptions\QueueException;

trait HasQueueValidation
{
    /**
     * Validate priority value.
     *
     * @throws QueueException
     */
    protected function validatePriority(string $priority): void
    {
        if (! preg_match('/^[a-z_-]+$/', $priority)) {
            throw QueueException::forIncorrectPriorityFormat();
        }

        if (strlen($priority) > 64) {
            throw QueueException::forTooLongPriorityName();
        }
    }

    /**
     * Validate delay value.
     *
     * @throws QueueException
     */
    protected function validateDelay(int $delay): void
    {
        if ($delay < 0) {
            throw QueueException::forIncorrectDelayValue();
        }
    }

    /**
     * Validate queue name.
     *
     * @throws QueueException
     */
    protected function validateQueue(string $queue): void
    {
        if (! preg_match('/^[a-z0-9_-]+$/', $queue)) {
            throw QueueException::forIncorrectQueueFormat();
        }

        if (strlen($queue) > 64) {
            throw QueueException::forTooLongQueueName();
        }
    }
}
