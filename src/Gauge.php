<?php

declare(strict_types=1);

namespace Rancoud\Prometheus;

use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Storage\Operation;

class Gauge extends Collector
{
    protected function type(): string
    {
        return 'gauge';
    }

    /**
     * Increments gauge.
     *
     * @throws CollectorException
     */
    public function inc(float|int $value = 1, array $labels = []): void
    {
        $this->assertLabelsMatchingDescriptor($labels, $this->descriptor);

        $this->storage->updateGauge($this->descriptor, Operation::Add, $value, $labels);
    }

    /**
     * Decrements gauge.
     *
     * @throws CollectorException
     */
    public function dec(float|int $value = 1, array $labels = []): void
    {
        $this->assertLabelsMatchingDescriptor($labels, $this->descriptor);

        $this->storage->updateGauge($this->descriptor, Operation::Sub, $value, $labels);
    }

    /**
     * Sets value of gauge.
     *
     * @throws CollectorException
     */
    public function set(float|int $value, array $labels = []): void
    {
        $this->assertLabelsMatchingDescriptor($labels, $this->descriptor);

        $this->storage->updateGauge($this->descriptor, Operation::Set, $value, $labels);
    }

    /**
     * Sets value of gauge with function \time() to use current Unix timestamp.
     *
     * @throws CollectorException
     */
    public function setToCurrentTime(array $labels = []): void
    {
        $this->assertLabelsMatchingDescriptor($labels, $this->descriptor);

        $this->storage->updateGauge($this->descriptor, Operation::Set, \time(), $labels);
    }
}
