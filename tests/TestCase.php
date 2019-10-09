<?php

namespace Laravel\Cashier\Tests;

use Orchestra\Testbench\Concerns\WithFactories;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    use WithFactories;

    public function setUp() : void
    {
        parent::setUp();

        $this->withFactories(__DIR__ . '/Fixtures/database/factories');
    }
}
