<?php

declare(strict_types=1);

namespace Rancoud\Prometheus;

use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Storage\Adapter;

abstract class Collector
{
    /** Storage engine. */
    protected Adapter $storage;

    /** Metric descriptor. */
    protected Descriptor $descriptor;

    public function __construct(Adapter $storage, Descriptor $descriptor)
    {
        $this->storage = $storage;
        $this->descriptor = $descriptor;
    }

    /** Returns metric type. */
    abstract protected function type(): string;

    /** Returns raw metrics (descriptor + samples) as iterable. */
    public function collect(): iterable
    {
        yield from $this->storage->collect(static::type(), $this->descriptor->name());
    }

    /** Returns text of metric as string. */
    public function expose(): string
    {
        $output = '';

        /* @noinspection PhpLoopCanBeReplacedWithImplodeInspection */
        foreach ($this->storage->expose(static::type(), $this->descriptor->name()) as $text) {
            $output .= $text;
        }

        return $output;
    }

    /** Returns metric name. */
    public function metricName(): string
    {
        return $this->descriptor->name();
    }

    /** Register in the default Registry. */
    public function register(): self
    {
        Registry::registerInDefault($this);

        return $this;
    }

    /**
     * Asserts if the count labels provided for each sample match with the count labels on the Descriptor.
     *
     * @throws CollectorException
     */
    protected function assertLabelsMatchingDescriptor(array $labels, Descriptor $descriptor): void
    {
        $countLabelsInDescriptor = $descriptor->labelsCount();
        $countLabels = \count($labels);

        if ($countLabels !== $countLabelsInDescriptor) {
            throw new CollectorException("Invalid labels: count labels given '" . $countLabels . "' are not matching the count labels defined '" . $countLabelsInDescriptor . "'.");
        }
    }
}
