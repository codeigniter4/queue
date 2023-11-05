<?php

namespace Michalsn\CodeIgniterQueue\Exceptions;

use RuntimeException;

final class QueueException extends RuntimeException
{
    public static function forIncorrectHandler(): static
    {
        return new self(lang('Queue.incorrectHandler'));
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
