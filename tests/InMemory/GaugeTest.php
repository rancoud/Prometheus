<?php

declare(strict_types=1);

namespace tests\InMemory;

use Rancoud\Prometheus\Storage\InMemory;
use tests\AbstractGauge;

/** @internal */
class GaugeTest extends AbstractGauge
{
    protected function setUp(): void
    {
        $this->storage = new InMemory();
    }
}
