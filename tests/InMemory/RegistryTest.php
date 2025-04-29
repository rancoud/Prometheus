<?php

declare(strict_types=1);

namespace tests\InMemory;

use Rancoud\Prometheus\Storage\InMemory;
use tests\AbstractRegistry;

/** @internal */
class RegistryTest extends AbstractRegistry
{
    protected function setUp(): void
    {
        $this->storage = new InMemory();
    }
}
