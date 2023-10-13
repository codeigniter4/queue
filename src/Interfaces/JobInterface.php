<?php

namespace Michalsn\CodeIgniterQueue\Interfaces;

interface JobInterface
{
    public function __construct(array $data);

    public function process();
}
