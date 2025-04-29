<?php

declare(strict_types=1);

namespace tests\InMemory;

use Rancoud\Prometheus\Storage\InMemory;
use tests\AbstractHistogram;

/** @internal */
class HistogramTest extends AbstractHistogram
{
    protected function setUp(): void
    {
        $this->storage = new InMemory();
    }
}
