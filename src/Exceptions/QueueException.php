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

namespace CodeIgniter\Queue\Exceptions;

use RuntimeException;

final class QueueException extends RuntimeException
{
    public static function forIncorrectHandler(): static
    {
        return new self(lang('Queue.incorrectHandler'));
    }

    public static function forIncorrectQueueFormat(): static
    {
        return new self(lang('Queue.incorrectQueueFormat'));
    }

    public static function forTooLongQueueName(): static
    {
        return new self(lang('Queue.tooLongQueueName'));
    }

    public static function forIncorrectJobHandler(): static
    {
        return new self(lang('Queue.incorrectJobHandler'));
    }

    public static function forIncorrectPriorityFormat(): static
    {
        return new self(lang('Queue.incorrectPriorityFormat'));
    }

    public static function forTooLongPriorityName(): static
    {
        return new self(lang('Queue.tooLongPriorityName'));
    }

    public static function forIncorrectQueuePriority(string $priority, string $queue): static
    {
        return new self(lang('Queue.incorrectQueuePriority', [$priority, $queue]));
    }
}
