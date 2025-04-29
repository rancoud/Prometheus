<?php

declare(strict_types=1);

namespace tests\InMemory;

use Rancoud\Prometheus\Storage\InMemory;
use tests\AbstractSummary;

/** @internal */
class SummaryTest extends AbstractSummary
{
    protected function setUp(): void
    {
        $this->storage = new InMemory();
    }
}
