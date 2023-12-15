<?php

namespace CodeIgniter\Queue\Interfaces;

interface JobInterface
{
    public function __construct(array $data);

    public function process();
}
