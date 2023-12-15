<?php

namespace CodeIgniter\Queue;

use JsonSerializable;

class Payload implements JsonSerializable
{
    public function __construct(protected string $job, protected array $data)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'job'  => $this->job,
            'data' => $this->data,
        ];
    }
}
