<?php

declare(strict_types=1);

namespace Rancoud\Prometheus;

use Rancoud\Prometheus\Exception\CollectorException;

class Counter extends Collector
{
    protected function type(): string
    {
        return 'counter';
    }

    /**
     * Increments counter.
     *
     * @throws CollectorException
     */
    public function inc(float|int $value = 1, array $labels = []): void
    {
        if ($value <= 0) {
            return;
        }

        $this->assertLabelsMatchingDescriptor($labels, $this->descriptor);

        $this->storage->updateCounter($this->descriptor, $value, $labels);
    }
}
