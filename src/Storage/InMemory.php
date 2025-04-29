<?php

declare(strict_types=1);

namespace Rancoud\Prometheus\Storage;

use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\StorageException;

/**
 * InMemory storage is not efficient because you can't keep data between requests.
 * But you can use it to have an example of how it works.
 * Always a new instance to avoid having data from other Collector and/or Registry.
 */
class InMemory implements Adapter
{
    /** List of counters metric. */
    protected array $counters = [];

    /** List of gauges metric. */
    protected array $gauges = [];

    /** List of histograms metric. */
    protected array $histograms = [];

    /** List of summaries metric. */
    protected array $summaries = [];

    /** @var callable|string Default \time() function for max age in summary metric. */
    protected $timeFunction = '\\time';

    // region Collect

    /** @throws StorageException */
    public function collect(string $metricType = '', string $metricName = ''): iterable
    {
        if ($metricType !== '' && $metricName !== '') {
            yield from $this->collectOne($metricType, $metricName);

            return;
        }

        foreach ($this->counters as $counter) {
            yield $counter;
        }

        foreach ($this->gauges as $gauge) {
            yield $gauge;
        }

        foreach ($this->histograms as $histogram) {
            yield $histogram;
        }

        foreach ($this->summaries as $summary) {
            yield $summary;
        }
    }

    /**
     * Returns only the specify metric.
     *
     * @throws StorageException
     */
    protected function collectOne(string $metricType, string $metricName): iterable
    {
        switch ($metricType) {
            case 'counter':
                if (\array_key_exists($metricName, $this->counters) === true) {
                    yield $this->counters[$metricName];
                }

                break;
            case 'gauge':
                if (\array_key_exists($metricName, $this->gauges) === true) {
                    yield $this->gauges[$metricName];
                }

                break;
            case 'histogram':
                if (\array_key_exists($metricName, $this->histograms) === true) {
                    yield $this->histograms[$metricName];
                }

                break;
            case 'summary':
                if (\array_key_exists($metricName, $this->summaries) === true) {
                    yield $this->summaries[$metricName];
                }

                break;
            default:
                throw new StorageException("Invalid metric '" . $metricType . "': it is not supported.");
        }
    }

    // endregion

    // region Expose

    /**
     * @throws \JsonException
     * @throws StorageException
     */
    public function expose(string $metricType = '', string $metricName = ''): iterable
    {
        if ($metricType !== '' && $metricName !== '') {
            yield from $this->exposeOne($metricType, $metricName);

            return;
        }

        yield from $this->exposeCounters();

        yield from $this->exposeGauges();

        yield from $this->exposeHistograms();

        yield from $this->exposeSummaries();
    }

    /**
     * Returns only the specify metric.
     *
     * @throws \JsonException
     * @throws StorageException
     */
    protected function exposeOne(string $metricType, string $metricName): iterable
    {
        switch ($metricType) {
            case 'counter':
                yield from $this->exposeCounters($metricName);

                break;
            case 'gauge':
                yield from $this->exposeGauges($metricName);

                break;
            case 'histogram':
                yield from $this->exposeHistograms($metricName);

                break;
            case 'summary':
                yield from $this->exposeSummaries($metricName);

                break;
            default:
                throw new StorageException("Invalid metric '" . $metricType . "': it is not supported.");
        }
    }

    // endregion

    // region Counter

    /**
     * Returns text of counters metric as iterable.
     *
     * @throws \JsonException
     * @throws StorageException
     */
    public function exposeCounters(string $metricName = ''): iterable
    {
        $hasToFilter = $metricName !== '';

        foreach ($this->counters as $name => $counter) {
            if ($hasToFilter && $metricName !== $name) {
                continue;
            }

            $help = $counter['descriptor']->exportHelp();
            if ($help !== '') {
                yield $help;
            }

            yield $counter['descriptor']->exportType('counter');

            foreach ($counter['samples'] as $labelValuesEncoded => $value) {
                yield $counter['descriptor']->exportValue($value, $this->decodeLabelValues($labelValuesEncoded));
            }
        }
    }

    /** @throws \JsonException */
    public function updateCounter(Descriptor $descriptor, float|int $value = 1, array $labelValues = []): void
    {
        $name = $descriptor->name();
        $labelValuesEncoded = $this->encodeLabelValues($labelValues);

        if (\array_key_exists($name, $this->counters) === false) {
            $this->counters[$name] = ['descriptor' => $descriptor, 'samples' => []];
        }

        if (\array_key_exists($labelValuesEncoded, $this->counters[$name]['samples']) === false) {
            $this->counters[$name]['samples'][$labelValuesEncoded] = 0;
        }

        $this->counters[$name]['samples'][$labelValuesEncoded] += $value;
    }

    // endregion

    // region Gauge

    /**
     * Returns text of gauges metric as iterable.
     *
     * @throws \JsonException
     * @throws StorageException
     */
    public function exposeGauges(string $metricName = ''): iterable
    {
        $hasToFilter = $metricName !== '';

        foreach ($this->gauges as $name => $gauge) {
            if ($hasToFilter && $metricName !== $name) {
                continue;
            }

            $help = $gauge['descriptor']->exportHelp();
            if ($help !== '') {
                yield $help;
            }

            yield $gauge['descriptor']->exportType('gauge');

            foreach ($gauge['samples'] as $labelValuesEncoded => $value) {
                yield $gauge['descriptor']->exportValue($value, $this->decodeLabelValues($labelValuesEncoded));
            }
        }
    }

    /** @throws \JsonException */
    public function updateGauge(Descriptor $descriptor, Operation $operation, float|int $value = 1, array $labelValues = []): void
    {
        $name = $descriptor->name();
        $labelValuesEncoded = $this->encodeLabelValues($labelValues);

        if (\array_key_exists($name, $this->gauges) === false) {
            $this->gauges[$name] = ['descriptor' => $descriptor, 'samples' => []];
        }

        if (\array_key_exists($labelValuesEncoded, $this->gauges[$name]['samples']) === false) {
            $this->gauges[$name]['samples'][$labelValuesEncoded] = 0;
        }

        switch ($operation) {
            case Operation::Set:
                $this->gauges[$name]['samples'][$labelValuesEncoded] = $value;

                break;
            case Operation::Add:
                $this->gauges[$name]['samples'][$labelValuesEncoded] += $value;

                break;
            case Operation::Sub:
                $this->gauges[$name]['samples'][$labelValuesEncoded] -= $value;

                break;
        }
    }

    // endregion

    // region Histogram

    /**
     * Returns text of histograms metric as iterable.
     *
     * @throws \JsonException
     * @throws StorageException
     */
    public function exposeHistograms(string $metricName = ''): iterable
    {
        $hasToFilter = $metricName !== '';

        foreach ($this->histograms as $name => $histogram) {
            if ($hasToFilter && $metricName !== $name) {
                continue;
            }

            $help = $histogram['descriptor']->exportHelp();
            if ($help !== '') {
                yield $help;
            }

            yield $histogram['descriptor']->exportType('histogram');

            $buckets = $histogram['descriptor']->buckets();
            $buckets[] = '+Inf';

            foreach ($histogram['samples'] as $labelValuesEncoded => $bucketsWithValue) {
                $labelValues = $this->decodeLabelValues($labelValuesEncoded);
                $accumulator = 0;

                foreach ($buckets as $bucket) {
                    $bucketAsString = (string) $bucket;
                    if (isset($bucketsWithValue[$bucketAsString])) {
                        $accumulator += $bucketsWithValue[$bucketAsString];
                    }

                    yield $histogram['descriptor']->exportHistogramValue($bucketAsString, $accumulator, $labelValues);
                }

                yield $histogram['descriptor']->exportValue($accumulator, $labelValues, '_count');

                yield $histogram['descriptor']->exportValue($bucketsWithValue['sum'], $labelValues, '_sum');
            }
        }
    }

    /** @throws \JsonException */
    public function updateHistogram(Descriptor $descriptor, float $value, array $labelValues = []): void
    {
        $name = $descriptor->name();
        $labelValuesEncoded = $this->encodeLabelValues($labelValues);

        if (\array_key_exists($name, $this->histograms) === false) {
            $this->histograms[$name] = ['descriptor' => $descriptor, 'samples' => []];
        }

        if (\array_key_exists($labelValuesEncoded, $this->histograms[$name]['samples']) === false) {
            $this->histograms[$name]['samples'][$labelValuesEncoded] = ['sum' => 0];
        }

        $this->histograms[$name]['samples'][$labelValuesEncoded]['sum'] += $value;

        $bucketToIncrease = '+Inf';
        $buckets = $descriptor->buckets();
        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $bucketToIncrease = (string) $bucket;

                break;
            }
        }

        if (\array_key_exists($bucketToIncrease, $this->histograms[$name]['samples'][$labelValuesEncoded]) === false) {
            $this->histograms[$name]['samples'][$labelValuesEncoded][$bucketToIncrease] = 0;
        }

        ++$this->histograms[$name]['samples'][$labelValuesEncoded][$bucketToIncrease];
    }

    // endregion

    // region Summary

    /**
     * Returns text of summaries metric as iterable.
     *
     * @throws \JsonException
     * @throws StorageException
     */
    public function exposeSummaries(string $metricName = ''): iterable
    {
        $hasToFilter = $metricName !== '';

        // first we clean expired summary
        foreach ($this->summaries as $keySummary => $summary) {
            if ($hasToFilter && $metricName !== $keySummary) {
                continue;
            }

            $ttlInSeconds = $summary['descriptor']->ttlInSeconds();

            foreach ($summary['samples'] as $labelValuesEncoded => $samples) {
                for ($idxSample = \count($samples) - 1; $idxSample >= 0; --$idxSample) {
                    if ($this->time() - $samples[$idxSample]['time'] <= $ttlInSeconds) {
                        continue;
                    }

                    $this->summaries[$keySummary]['samples'][$labelValuesEncoded] = \array_slice($samples, $idxSample + 1);

                    break;
                }

                if (\count($this->summaries[$keySummary]['samples'][$labelValuesEncoded]) === 0) {
                    unset($this->summaries[$keySummary]);
                }
            }
        }

        // then we can collect
        foreach ($this->summaries as $name => $summary) {
            if ($hasToFilter && $metricName !== $name) {
                continue;
            }

            $help = $summary['descriptor']->exportHelp();
            if ($help !== '') {
                yield $help;
            }

            yield $summary['descriptor']->exportType('summary');

            $quantiles = $summary['descriptor']->quantiles();

            foreach ($summary['samples'] as $labelValuesEncoded => $samples) {
                $labelValues = $this->decodeLabelValues($labelValuesEncoded);

                \usort($samples, static function (array $value1, array $value2) {
                    if ($value1['value'] === $value2['value']) {
                        return 0;
                    }

                    return ($value1['value'] < $value2['value']) ? -1 : 1;
                });

                $values = \array_column($samples, 'value');

                foreach ($quantiles as $quantile) {
                    yield $summary['descriptor']->exportSummaryValue($quantile, $values, $labelValues);
                }

                yield $summary['descriptor']->exportValue(\count($values), $labelValues, '_count');

                yield $summary['descriptor']->exportValue(\array_sum($values), $labelValues, '_sum');
            }
        }
    }

    /** @throws \JsonException */
    public function updateSummary(Descriptor $descriptor, float $value, array $labelValues = []): void
    {
        $name = $descriptor->name();
        $labelValuesEncoded = $this->encodeLabelValues($labelValues);

        if (\array_key_exists($name, $this->summaries) === false) {
            $this->summaries[$name] = ['descriptor' => $descriptor, 'samples' => []];
        }

        if (\array_key_exists($labelValuesEncoded, $this->summaries[$name]['samples']) === false) {
            $this->summaries[$name]['samples'][$labelValuesEncoded] = [];
        }

        $this->summaries[$name]['samples'][$labelValuesEncoded][] = [
            'time'  => $this->time(),
            'value' => $value
        ];
    }

    // endregion

    // region Wipe storage

    public function wipeStorage(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
        $this->summaries = [];
    }

    // endregion

    // region Label Values

    /**
     * Encode label values into json_encode.
     *
     * @throws \JsonException
     */
    protected function encodeLabelValues(array $labelValues): string
    {
        return \json_encode($labelValues, \JSON_THROW_ON_ERROR, 1);
    }

    /**
     * Decode label values from json_decode.
     *
     * @throws \JsonException
     */
    protected function decodeLabelValues(string $labelValuesEncoded): array
    {
        return \json_decode($labelValuesEncoded, null, 2, \JSON_THROW_ON_ERROR);
    }

    // endregion

    // region Time function

    public function setTimeFunction(callable|string $time): void
    {
        $this->timeFunction = $time;
    }

    /** Used for summary metric. */
    protected function time(): int
    {
        return ($this->timeFunction)();
    }

    // endregion
}
