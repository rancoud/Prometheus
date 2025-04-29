<?php

declare(strict_types=1);

namespace Rancoud\Prometheus;

use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Storage\Adapter;

class Summary extends Collector
{
    /** @throws CollectorException */
    public function __construct(Adapter $storage, Descriptor $descriptor)
    {
        if (\in_array('quantile', $descriptor->labels(), true)) {
            throw new CollectorException("Invalid label name: summary label 'quantile' is reserved.");
        }

        parent::__construct($storage, $descriptor);
    }

    protected function type(): string
    {
        return 'summary';
    }

    /**
     * Adds a new sample.
     *
     * @throws CollectorException
     */
    public function observe(float $value, array $labels = []): void
    {
        $this->assertLabelsMatchingDescriptor($labels, $this->descriptor);

        $this->storage->updateSummary($this->descriptor, $value, $labels);
    }
}
