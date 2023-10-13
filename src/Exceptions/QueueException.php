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
}