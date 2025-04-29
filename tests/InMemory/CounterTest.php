<?php

declare(strict_types=1);

namespace tests\InMemory;

use Rancoud\Prometheus\Storage\InMemory;
use tests\AbstractCounter;

/** @internal */
class CounterTest extends AbstractCounter
{
    protected function setUp(): void
    {
        $this->storage = new InMemory();
    }
}
