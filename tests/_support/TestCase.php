<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

abstract class TestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;

    protected function setUp(): void
    {
        $this->resetServices();

        parent::setUp();
    }
}
