<?php

declare(strict_types=1);

namespace Rancoud\Prometheus;

use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Storage\Adapter;

class Histogram extends Collector
{
    /** @throws CollectorException */
    public function __construct(Adapter $storage, Descriptor $descriptor)
    {
        if (\in_array('le', $descriptor->labels(), true)) {
            throw new CollectorException("Invalid label name: histogram label 'le' is reserved.");
        }

        parent::__construct($storage, $descriptor);
    }

    protected function type(): string
    {
        return 'histogram';
    }

    /**
     * Adds a new sample.
     *
     * @throws CollectorException
     */
    public function observe(float $value, array $labels = []): void
    {
        $this->assertLabelsMatchingDescriptor($labels, $this->descriptor);

        $this->storage->updateHistogram($this->descriptor, $value, $labels);
    }

    // region Helpers

    /**
     * Generates linear buckets.
     * Creates 'count' regular buckets, each 'width' wide, where the lowest bucket has an upper bound of 'start'.
     *
     * @throws CollectorException
     */
    public static function linearBuckets(float $start, float $width, int $countBuckets): array
    {
        if ($start < 0) {
            throw new CollectorException("Invalid argument 'start': it must be equal or greater than 0.");
        }

        if ($width <= 0) {
            throw new CollectorException("Invalid argument 'width': it must be greater than 0.");
        }

        if ($countBuckets < 1) {
            throw new CollectorException("Invalid argument 'countBuckets': it must be greater than 0.");
        }

        $buckets = [];

        for ($idxBucket = 0; $idxBucket < $countBuckets; ++$idxBucket) {
            $buckets[$idxBucket] = $start;
            $start += $width;
        }

        return $buckets;
    }

    /**
     * Generates exponential buckets.
     * Creates 'count' regular buckets, where the lowest bucket has an upper bound of 'start'
     * and each following bucket's upper bound is 'factor' times the previous bucket's upper bound.
     *
     * @throws CollectorException
     */
    public static function exponentialBuckets(float $start, float $growthFactor, int $countBuckets): array
    {
        if ($start <= 0) {
            throw new CollectorException("Invalid argument 'start': it must be greater than 0.");
        }

        if ($growthFactor <= 1) {
            throw new CollectorException("Invalid argument 'growthFactor': it must be greater than 1.");
        }

        if ($countBuckets < 1) {
            throw new CollectorException("Invalid argument 'countBuckets': it must be greater than 0.");
        }

        $buckets = [];

        for ($idxBucket = 0; $idxBucket < $countBuckets; ++$idxBucket) {
            $buckets[$idxBucket] = $start;
            $start *= $growthFactor;
        }

        return $buckets;
    }

    // endregion
}
