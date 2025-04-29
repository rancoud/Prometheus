<?php

declare(strict_types=1);

namespace Rancoud\Prometheus;

use Rancoud\Prometheus\Exception\DescriptorException;

/**
 * Describe metric with name, labels and specific metrics setters.
 * Also has functions used when collect data for exposition.
 */
class Descriptor
{
    /** Metric name. */
    protected string $name = '';

    /** Labels for metric. */
    protected array $labels = [];

    /** Describes metric. */
    protected string $help = '';

    /** Default buckets for histogram metric. */
    protected array $buckets = [.005, .01, .025, .05, 0.075, .1, .25, .5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0];

    /** Time To Live in seconds to keep samples for summary metric when collecting. */
    protected int $ttlInSeconds = 600;

    /** Default quantiles for summary metric. */
    protected array $quantiles = [0.01, 0.05, 0.5, 0.95, 0.99];

    /** @throws DescriptorException */
    public function __construct(string $name, array $labels = [])
    {
        self::assertValidName($name);

        foreach ($labels as $label) {
            self::assertValidLabel($label);
        }

        $this->name = $name;
        $this->labels = $labels;
    }

    /**
     * Asserts if metric is valid otherwise throws DescriptorException.
     *
     * @see https://prometheus.io/docs/concepts/data_model/#metric-names-and-labels
     *
     * @throws DescriptorException
     */
    protected static function assertValidName(string $name): void
    {
        if (\preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:]+$/', $name) !== 1) {
            throw new DescriptorException("Invalid metric name '" . $name . "': it need to follow that pattern [a-zA-Z_:][a-zA-Z0-9_:].");
        }
    }

    /**
     * Asserts if label is valid otherwise throws DescriptorException.
     *
     * @see https://prometheus.io/docs/concepts/data_model/#metric-names-and-labels
     *
     * @throws DescriptorException
     */
    protected static function assertValidLabel(string $label): void
    {
        if (\mb_strpos($label, '__') === 0) {
            throw new DescriptorException("Invalid label name '" . $label . "': it can't have a label name that starts with '__'.");
        }

        if (\preg_match('/^[a-zA-Z_][a-zA-Z0-9_]+$/', $label) !== 1) {
            throw new DescriptorException("Invalid label name '" . $label . "': it need to follow that pattern [a-zA-Z_][a-zA-Z0-9_].");
        }
    }

    // region Setters

    /** When exposed it will output a line #HELP {your message}. */
    public function setHelp(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    /**
     * Set histogram buckets instead of using default buckets.
     *
     * @throws DescriptorException
     */
    public function setHistogramBuckets(array $buckets): self
    {
        if (\count($buckets) === 0) {
            throw new DescriptorException('Invalid histogram buckets: it must have at least one bucket.');
        }

        for ($idxBucket = 0, $maxBucket = \count($buckets) - 1; $idxBucket < $maxBucket; ++$idxBucket) {
            if (!(\is_float($buckets[$idxBucket]) || \is_int($buckets[$idxBucket]))) {
                throw new DescriptorException('Invalid histogram bucket: at index #' . $idxBucket . ' value must be a float or a int.');
            }

            if ($buckets[$idxBucket] >= $buckets[$idxBucket + 1]) {
                throw new DescriptorException('Invalid histogram buckets: it must be in increasing order. ' .
                    'Failed on ' . $buckets[$idxBucket] . ' >= ' . $buckets[$idxBucket + 1] . '.');
            }
        }

        if (!(\is_float($buckets[$idxBucket]) || \is_int($buckets[$idxBucket]))) {
            throw new DescriptorException('Invalid histogram bucket: at index #' . $idxBucket . ' value must be a float or a int.');
        }

        $this->buckets = $buckets;

        return $this;
    }

    /**
     * Set summary TTL instead of using default TTL.
     *
     * @throws DescriptorException
     */
    public function setSummaryTTL(int $ttlInSeconds): self
    {
        if ($ttlInSeconds <= 0) {
            throw new DescriptorException("Invalid TTL value '" . $ttlInSeconds . "': it must be greater than 0.");
        }

        $this->ttlInSeconds = $ttlInSeconds;

        return $this;
    }

    /**
     * Set summary quantiles instead of using default quantiles.
     *
     * @throws DescriptorException
     */
    public function setSummaryQuantiles(array $quantiles): self
    {
        if (\count($quantiles) === 0) {
            throw new DescriptorException('Invalid summary quantiles: it must have at least one quantile.');
        }

        for ($idxQuantile = 0, $maxBucket = \count($quantiles) - 1; $idxQuantile < $maxBucket; ++$idxQuantile) {
            if (!(\is_float($quantiles[$idxQuantile]) || \is_int($quantiles[$idxQuantile]))) {
                throw new DescriptorException('Invalid summary quantile: at index #' . $idxQuantile . ' value must be a float or a int.');
            }

            if ($quantiles[$idxQuantile] <= 0 || $quantiles[$idxQuantile] >= 1) {
                throw new DescriptorException('Invalid summary quantile: at index #' . $idxQuantile . ' value must be between 0 and 1.');
            }

            if ($quantiles[$idxQuantile] >= $quantiles[$idxQuantile + 1]) {
                throw new DescriptorException('Invalid summary quantiles: it must be in increasing order. ' .
                    'Failed on ' . $quantiles[$idxQuantile] . ' >= ' . $quantiles[$idxQuantile + 1] . '.');
            }
        }

        if (!(\is_float($quantiles[$idxQuantile]) || \is_int($quantiles[$idxQuantile]))) {
            throw new DescriptorException('Invalid summary quantile: at index #' . $idxQuantile . ' value must be a float or a int.');
        }

        if ($quantiles[$idxQuantile] <= 0 || $quantiles[$idxQuantile] >= 1) {
            throw new DescriptorException('Invalid summary quantile: at index #' . $idxQuantile . ' value must be between 0 and 1.');
        }

        $this->quantiles = $quantiles;

        return $this;
    }

    // endregion

    // region Helpers

    /** Returns name. */
    public function name(): string
    {
        return $this->name;
    }

    /** Returns labels. */
    public function labels(): array
    {
        return $this->labels;
    }

    /** Returns labels count. */
    public function labelsCount(): int
    {
        return \count($this->labels);
    }

    /** Returns histogram buckets. */
    public function buckets(): array
    {
        return $this->buckets;
    }

    /** Returns summary quantiles. */
    public function quantiles(): array
    {
        return $this->quantiles;
    }

    /** Returns summary TTL. */
    public function ttlInSeconds(): int
    {
        return $this->ttlInSeconds;
    }

    /** Returns help text. */
    public function help(): string
    {
        return $this->help;
    }

    // endregion

    // region Expose

    /** Exports HELP. */
    public function exportHelp(): string
    {
        if ($this->help === '') {
            return '';
        }

        return '#HELP ' . $this->name . ' ' . $this->help . "\n";
    }

    /** Exports TYPE. */
    public function exportType(string $type): string
    {
        return '#TYPE ' . $this->name . ' ' . $type . "\n";
    }

    /** Exports value (counter, gauge, histogram _sum and _count, summary _sum and _count). */
    public function exportValue(float|int $value, array $labelValues, string $suffixName = ''): string
    {
        $countLabels = \count($this->labels);
        if ($countLabels === 0) {
            return $this->name . $suffixName . ' ' . $value . "\n";
        }

        $output = $this->name . $suffixName . '{';

        $labels = [];
        for ($idxLabel = 0; $idxLabel < $countLabels; ++$idxLabel) {
            $labels[] = $this->labels[$idxLabel] . '="' . $this->escapeLabelValue($labelValues[$idxLabel]) . '"';
        }

        $output .= \implode(',', $labels) . '} ' . $value . "\n";

        return $output;
    }

    /** Exports value (histogram). */
    public function exportHistogramValue(string $bucket, int $value, array $labelValues): string
    {
        $countLabels = \count($this->labels);

        $output = $this->name . '_bucket{';

        $labels = [];
        for ($idxLabel = 0; $idxLabel < $countLabels; ++$idxLabel) {
            $labels[] = $this->labels[$idxLabel] . '="' . $this->escapeLabelValue($labelValues[$idxLabel]) . '"';
        }
        $labels[] = 'le="' . $bucket . '"';

        $output .= \implode(',', $labels) . '} ' . $value . "\n";

        return $output;
    }

    /** Exports value (summary). */
    public function exportSummaryValue(float $quantile, array $values, array $labelValues): string
    {
        $countLabels = \count($this->labels);

        $output = $this->name . '{';

        $labels = [];
        for ($idxLabel = 0; $idxLabel < $countLabels; ++$idxLabel) {
            $labels[] = $this->labels[$idxLabel] . '="' . $this->escapeLabelValue($labelValues[$idxLabel]) . '"';
        }
        $labels[] = 'quantile="' . $quantile . '"';

        $output .= \implode(',', $labels) . '} ' . $this->quantile($values, $quantile) . "\n";

        return $output;
    }

    /**
     * Computes quantile when exporting.
     *
     * @see https://www.php.net/manual/fr/function.stats-stat-percentile.php#79752.
     *
     * @param float[] $values must be sorted
     */
    protected function quantile(array $values, float $quantile): float
    {
        $count = \count($values);
        $idx = \floor($count * $quantile);
        $res = $count * $quantile - $idx;
        if (0.0 === $res) {
            return $values[$idx - 1];
        }

        return $values[$idx];
    }

    /** Escape label values for expose. */
    protected function escapeLabelValue(string $labelValue): string
    {
        return \str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $labelValue);
    }

    // endregion
}
