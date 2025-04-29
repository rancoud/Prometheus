<?php

declare(strict_types=1);

namespace Rancoud\Prometheus\Storage;

use Rancoud\Prometheus\Descriptor;

interface Adapter
{
    /**
     * Returns metrics (counter, gauge, histogram and summary) as iterable.
     * If metric type and name is provided it will return only the specify metric.
     */
    public function collect(string $metricType = '', string $metricName = ''): iterable;

    /**
     * Returns text of metrics (counter, gauge, histogram and summary) as iterable.
     * If metric type and name is provided it will return only the specify metric.
     */
    public function expose(string $metricType = '', string $metricName = ''): iterable;

    /** Updates counter metric. */
    public function updateCounter(Descriptor $descriptor, float|int $value = 1, array $labelValues = []): void;

    /** Updates gauge metric. */
    public function updateGauge(Descriptor $descriptor, Operation $operation, float|int $value = 1, array $labelValues = []): void;

    /** Adds sample to histogram metric. */
    public function updateHistogram(Descriptor $descriptor, float $value, array $labelValues = []): void;

    /** Adds sample to summary metric. */
    public function updateSummary(Descriptor $descriptor, float $value, array $labelValues = []): void;

    /** Removes all data saved. */
    public function wipeStorage(): void;

    /** Overrides Time Function for summary metric. */
    public function setTimeFunction(callable|string $time): void;
}
