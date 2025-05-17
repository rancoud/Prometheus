<?php

declare(strict_types=1);

namespace Rancoud\Prometheus\Storage;

use Rancoud\Database\Database;
use Rancoud\Database\DatabaseException;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\DescriptorException;
use Rancoud\Prometheus\Exception\StorageException;

class SQLite implements Adapter
{
    /** @var callable|string Default \time() function for max age in summary metric. */
    protected $timeFunction = '\\time';

    /** Database. */
    protected Database $database;

    /** Prefix to add to table names. */
    protected string $prefixTableName = '';

    // region Construct

    /**
     * @throws DatabaseException
     * @throws StorageException
     */
    public function __construct(Database $database, string $prefix = '', bool $createsTables = true)
    {
        $this->database = $database;

        if ($prefix !== '') {
            if (\preg_match('/^[a-zA-Z0-9_-]+$/', $prefix) !== 1) {
                throw new StorageException("Invalid prefix name '" . $prefix . "': it need to follow that pattern [a-zA-Z0-9_-].");
            }

            $this->prefixTableName = $prefix . '_';
        }

        if ($createsTables) {
            $this->createTables();
        }
    }

    /**
     * Creates tables.
     *
     * @throws DatabaseException
     */
    protected function createTables(): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS `{$this->prefixTableName}prometheus_metadata` (
                `name` varchar(255) NOT NULL,
                `type` varchar(9) NOT NULL,
                `help` varchar(255) NULL,
                `labels` TEXT NULL,
                `buckets` varchar(255) NULL,
                `ttl` int DEFAULT 600 NULL,
                `quantiles` varchar(255) NULL,
                PRIMARY KEY (`name`)
            );
            SQL;

        $this->database->exec($sql);

        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS `{$this->prefixTableName}prometheus_values` (
                `name` varchar(255) NOT NULL,
                `label_values` TEXT NULL,
                `value` double DEFAULT 0.0,
                PRIMARY KEY (`name`, `label_values`)
            );
            SQL;

        $this->database->exec($sql);

        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS `{$this->prefixTableName}prometheus_histograms` (
                `name` varchar(255) NOT NULL,
                `label_values` TEXT NULL,
                `value` double DEFAULT 0.0,
                `bucket` varchar(10) NOT NULL,
                PRIMARY KEY (`name`,`label_values`, `bucket`)
            );
            SQL;

        $this->database->exec($sql);

        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS `{$this->prefixTableName}prometheus_summaries` (
                `name` varchar(255) NOT NULL,
                `label_values` TEXT NULL,
                `value` double DEFAULT 0.0,
                `time` timestamp NOT NULL
            );
            SQL;

        $this->database->exec($sql);

        $sql = <<<SQL
            CREATE INDEX IF NOT EXISTS `name` ON `{$this->prefixTableName}prometheus_summaries`(`name`);
            SQL;

        $this->database->exec($sql);
    }

    // endregion

    // region Collect

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    public function collect(string $metricType = '', string $metricName = ''): iterable
    {
        if ($metricType !== '' && $metricName !== '') {
            yield from $this->collectOne($metricType, $metricName);

            return;
        }

        $counters = $this->getMetadataAndValuesForCountersOrGauges('counter');
        foreach ($counters as $counter) {
            yield $counter;
        }

        $gauges = $this->getMetadataAndValuesForCountersOrGauges('gauge');
        foreach ($gauges as $gauge) {
            yield $gauge;
        }

        $histograms = $this->getMetadataAndValuesForHistograms();
        foreach ($histograms as $histogram) {
            yield $histogram;
        }

        $summaries = $this->getMetadataAndValuesForSummaries();
        foreach ($summaries as $summary) {
            yield $summary;
        }
    }

    /**
     * Returns only the specify metric.
     *
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    protected function collectOne(string $metricType, string $metricName): iterable
    {
        switch ($metricType) {
            case 'counter':
                $counters = $this->getMetadataAndValuesForCountersOrGauges('counter', $metricName);

                if (\array_key_exists($metricName, $counters) === true) {
                    yield $counters[$metricName];
                }

                break;
            case 'gauge':
                $gauges = $this->getMetadataAndValuesForCountersOrGauges('gauge', $metricName);

                if (\array_key_exists($metricName, $gauges) === true) {
                    yield $gauges[$metricName];
                }

                break;
            case 'histogram':
                $histograms = $this->getMetadataAndValuesForHistograms($metricName);

                if (\array_key_exists($metricName, $histograms) === true) {
                    yield $histograms[$metricName];
                }

                break;
            case 'summary':
                $summaries = $this->getMetadataAndValuesForSummaries($metricName);

                if (\array_key_exists($metricName, $summaries) === true) {
                    yield $summaries[$metricName];
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
     * @throws DatabaseException
     * @throws DescriptorException
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
     * @throws DatabaseException
     * @throws DescriptorException
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
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    public function exposeCounters(string $metricName = ''): iterable
    {
        $counters = $this->getMetadataAndValuesForCountersOrGauges('counter', $metricName);

        foreach ($counters as $counter) {
            $help = $counter['descriptor']->exportHelp();
            if ($help !== '') {
                yield $help;
            }

            yield $counter['descriptor']->exportType('counter');

            foreach ($counter['samples'] as $sample) {
                yield $counter['descriptor']->exportValue($sample['value'], $sample['label_values']);
            }
        }
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     */
    public function updateCounter(Descriptor $descriptor, float|int $value = 1, array $labelValues = []): void
    {
        $this->updateMetadata($descriptor, 'counter');

        $sql = <<<SQL
            INSERT INTO `{$this->prefixTableName}prometheus_values`(`name`, `label_values`, `value`)
            VALUES(:name, :label_values, :value)
            ON CONFLICT(name, label_values) DO UPDATE SET
                `value` = `value` + excluded.value;
            SQL;

        $this->database->insert($sql, [
            'name'         => $descriptor->name(),
            'label_values' => $this->encodeLabelValues($labelValues),
            'value'        => $value
        ]);
    }

    // endregion

    // region Gauge

    /**
     * Returns text of gauges metric as iterable.
     *
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    public function exposeGauges(string $metricName = ''): iterable
    {
        $gauges = $this->getMetadataAndValuesForCountersOrGauges('gauge', $metricName);

        foreach ($gauges as $gauge) {
            $help = $gauge['descriptor']->exportHelp();
            if ($help !== '') {
                yield $help;
            }

            yield $gauge['descriptor']->exportType('gauge');

            foreach ($gauge['samples'] as $sample) {
                yield $gauge['descriptor']->exportValue($sample['value'], $sample['label_values']);
            }
        }
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     */
    public function updateGauge(Descriptor $descriptor, Operation $operation, float|int $value = 1, array $labelValues = []): void
    {
        $this->updateMetadata($descriptor, 'gauge');

        $sqlOperation = '';
        switch ($operation) {
            case Operation::Set:
                $sqlOperation = 'excluded.value';

                break;
            case Operation::Add:
                $sqlOperation = '`value` + excluded.value';

                break;
            case Operation::Sub:
                $sqlOperation = '`value` + excluded.value';
                $value = -$value;

                break;
        }
        $sql = <<<SQL
            INSERT INTO `{$this->prefixTableName}prometheus_values`(`name`, `label_values`, `value`)
            VALUES(:name, :label_values, :value)
            ON CONFLICT(name, label_values) DO UPDATE SET
                `value` = {$sqlOperation};
            SQL;

        $this->database->insert($sql, [
            'name'         => $descriptor->name(),
            'label_values' => $this->encodeLabelValues($labelValues),
            'value'        => $value
        ]);
    }

    // endregion

    // region Histogram

    /**
     * Returns text of histograms metric as iterable.
     *
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     */
    public function exposeHistograms(string $metricName = ''): iterable
    {
        $histograms = $this->getMetadataAndValuesForHistograms($metricName);

        foreach ($histograms as $histogram) {
            $help = $histogram['descriptor']->exportHelp();
            if ($help !== '') {
                yield $help;
            }

            yield $histogram['descriptor']->exportType('histogram');

            $buckets = $histogram['descriptor']->buckets();
            $buckets[] = '+Inf';

            foreach ($histogram['samples'] as $labelValuesEncoded => $bucketsWithValue) {
                $accumulator = 0;

                $labelValues = $this->decodeLabelValues($labelValuesEncoded);

                foreach ($buckets as $bucket) {
                    $bucketAsString = (string) $bucket;
                    if (isset($bucketsWithValue[$bucketAsString])) {
                        $accumulator += (int) $bucketsWithValue[$bucketAsString];
                    }

                    yield $histogram['descriptor']->exportHistogramValue($bucketAsString, $accumulator, $labelValues);
                }

                yield $histogram['descriptor']->exportValue($accumulator, $labelValues, '_count');

                yield $histogram['descriptor']->exportValue($bucketsWithValue['sum'], $labelValues, '_sum');
            }
        }
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     */
    public function updateHistogram(Descriptor $descriptor, float $value, array $labelValues = []): void
    {
        $bucketToIncrease = '+Inf';
        $buckets = $descriptor->buckets();
        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $bucketToIncrease = (string) $bucket;

                break;
            }
        }

        $this->updateMetadata($descriptor, 'histogram');

        $sql = <<<SQL
            INSERT INTO `{$this->prefixTableName}prometheus_histograms`(`name`, `label_values`, `value`, `bucket`)
            VALUES(:name, :label_values, :value, :bucket)
            ON CONFLICT(name, label_values, bucket) DO UPDATE SET
                `value` = `value` + excluded.value;
            SQL;

        $this->database->insert($sql, [
            'name'         => $descriptor->name(),
            'label_values' => $this->encodeLabelValues($labelValues),
            'value'        => 1,
            'bucket'       => $bucketToIncrease
        ]);

        $this->database->insert($sql, [
            'name'         => $descriptor->name(),
            'label_values' => $this->encodeLabelValues($labelValues),
            'value'        => $value,
            'bucket'       => 'sum'
        ]);
    }

    // endregion

    // region Summary

    /**
     * Returns text of summaries metric as iterable.
     *
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     */
    public function exposeSummaries(string $metricName = ''): iterable
    {
        $this->deleteExpiredSummaries();

        $summaries = $this->getMetadataAndValuesForSummaries($metricName);

        foreach ($summaries as $summary) {
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

    /**
     * @throws \JsonException
     * @throws DatabaseException
     */
    public function updateSummary(Descriptor $descriptor, float $value, array $labelValues = []): void
    {
        $this->updateMetadata($descriptor, 'summary');

        $sql = <<<SQL
            INSERT INTO `{$this->prefixTableName}prometheus_summaries`(`name`, `label_values`, `value`, `time`)
            VALUES(:name, :label_values, :value, :time);
            SQL;

        $this->database->insert($sql, [
            'name'         => $descriptor->name(),
            'label_values' => $this->encodeLabelValues($labelValues),
            'value'        => $value,
            'time'         => $this->time()
        ]);
    }

    // endregion

    // region Database

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     */
    protected function getMetadataAndValuesForCountersOrGauges(string $type, string $metricName = ''): array
    {
        if ($metricName !== '') {
            $sql = <<<SQL
                SELECT * FROM `{$this->prefixTableName}prometheus_metadata` AS `pm`
                INNER JOIN `prometheus_values` AS `pv` ON `pv`.`name` = `pm`.`name`
                WHERE `pm`.`name` = :name
                SQL;

            $rows = $this->database->selectAll($sql, ['name' => $metricName]);
        } else {
            $sql = <<<SQL
                SELECT * FROM `{$this->prefixTableName}prometheus_metadata` AS `pm`
                INNER JOIN `prometheus_values` AS `pv` ON `pv`.`name` = `pm`.`name`
                WHERE `pm`.`type` = :type
                SQL;

            $rows = $this->database->selectAll($sql, ['type' => $type]);
        }

        $output = [];
        foreach ($rows as $row) {
            if (\array_key_exists($row['name'], $output) === true) {
                goto samples;
            }

            // descriptor
            $labels = [];
            if ($row['labels'] !== '') {
                $labels = \explode(',', $row['labels']);
            }

            $descriptor = new Descriptor($row['name'], $labels)->setHelp($row['help']);

            $output[$row['name']] = [
                'descriptor' => $descriptor,
                'samples'    => []
            ];

            samples:
            $output[$row['name']]['samples'][] = [
                'value'        => $row['value'],
                'label_values' => $this->decodeLabelValues($row['label_values'])
            ];
        }

        return $output;
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     */
    protected function getMetadataAndValuesForHistograms(string $metricName = ''): array
    {
        if ($metricName !== '') {
            $sql = <<<SQL
                SELECT * FROM `{$this->prefixTableName}prometheus_metadata` AS `pm`
                LEFT JOIN `prometheus_histograms` AS `ph` ON `ph`.`name` = `pm`.`name`
                WHERE `pm`.`name` = :name
                SQL;

            $rows = $this->database->selectAll($sql, ['name' => $metricName]);
        } else {
            $sql = <<<SQL
                SELECT * FROM `{$this->prefixTableName}prometheus_metadata` AS `pm`
                LEFT JOIN `prometheus_histograms` AS `ph` ON `ph`.`name` = `pm`.`name`
                WHERE `pm`.`type` = 'histogram'
                SQL;

            $rows = $this->database->selectAll($sql);
        }

        $output = [];
        foreach ($rows as $row) {
            if (\array_key_exists($row['name'], $output) === true) {
                goto samples;
            }

            // descriptor
            $labels = [];
            if ($row['labels'] !== '') {
                $labels = \explode(',', $row['labels']);
            }

            $descriptor = new Descriptor($row['name'], $labels)->setHelp($row['help']);

            if ($row['buckets'] !== '') {
                $descriptor->setHistogramBuckets(\json_decode($row['buckets'], null, 2, \JSON_THROW_ON_ERROR));
            }

            $output[$row['name']] = [
                'descriptor' => $descriptor,
                'samples'    => []
            ];

            samples:
            if (\array_key_exists($row['label_values'], $output[$row['name']]['samples']) === false) {
                $output[$row['name']]['samples'][$row['label_values']] = [];
            }

            if (\array_key_exists((string) $row['bucket'], $output[$row['name']]['samples'][$row['label_values']]) === false) {
                $output[$row['name']]['samples'][$row['label_values']][(string) $row['bucket']] = 0;
            }

            $output[$row['name']]['samples'][$row['label_values']][(string) $row['bucket']] = $row['value'];
        }

        return $output;
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     */
    protected function getMetadataAndValuesForSummaries(string $metricName = ''): array
    {
        if ($metricName !== '') {
            $sql = <<<SQL
                SELECT `pm`.*, `ps`.`label_values`, `ps`.`value`, `ps`.`time` FROM `{$this->prefixTableName}prometheus_metadata` AS `pm`
                INNER JOIN `prometheus_summaries` AS `ps` ON `ps`.`name` = `pm`.`name`
                WHERE `pm`.`name` = :name
                SQL;

            $rows = $this->database->selectAll($sql, ['name' => $metricName]);
        } else {
            $sql = <<<SQL
                SELECT `pm`.*, `ps`.`label_values`, `ps`.`value`, `ps`.`time` FROM `{$this->prefixTableName}prometheus_metadata` AS `pm`
                INNER JOIN `prometheus_summaries` AS `ps` ON `ps`.`name` = `pm`.`name`
                WHERE `pm`.`type` = 'summary'
                SQL;

            $rows = $this->database->selectAll($sql);
        }

        $output = [];
        foreach ($rows as $row) {
            if (\array_key_exists($row['name'], $output) === true) {
                goto samples;
            }

            // descriptor
            $labels = [];
            if ($row['labels'] !== '') {
                $labels = \explode(',', $row['labels']);
            }

            $descriptor = new Descriptor($row['name'], $labels)->setHelp($row['help']);

            if ($row['quantiles'] !== '') {
                $descriptor->setSummaryQuantiles(\json_decode($row['quantiles'], null, 2, \JSON_THROW_ON_ERROR));
            }

            $descriptor->setSummaryTTL($row['ttl']);

            $output[$row['name']] = [
                'descriptor' => $descriptor,
                'samples'    => []
            ];

            samples:
            if (\array_key_exists($row['label_values'], $output[$row['name']]['samples']) === false) {
                $output[$row['name']]['samples'][$row['label_values']] = [];
            }

            $output[$row['name']]['samples'][$row['label_values']][] = [
                'value' => $row['value'],
                'time'  => $row['time']
            ];
        }

        return $output;
    }

    /**
     * Adds Metric informations in table prometheus_metadata.
     *
     * @throws \JsonException
     * @throws DatabaseException
     */
    protected function updateMetadata(Descriptor $descriptor, string $metricType): void
    {
        $sql = <<<SQL
            INSERT INTO `{$this->prefixTableName}prometheus_metadata` (`name`, `type`, `help`, `labels`, `buckets`, `ttl`, `quantiles`)
            VALUES(:name, :type, :help, :labels, :buckets, :ttl, :quantiles)
            ON CONFLICT(name) DO NOTHING;
            SQL;

        $this->database->insert($sql, [
            'name'      => $descriptor->name(),
            'type'      => $metricType,
            'help'      => $descriptor->help(),
            'labels'    => \implode(',', $descriptor->labels()),
            'buckets'   => \json_encode($descriptor->buckets(), \JSON_THROW_ON_ERROR, 1),
            'ttl'       => $descriptor->ttlInSeconds(),
            'quantiles' => \json_encode($descriptor->quantiles(), \JSON_THROW_ON_ERROR, 1)
        ]);
    }

    /**
     * Remove all expired summaries sample according to the TTL.
     *
     * @throws DatabaseException
     */
    public function deleteExpiredSummaries(): void
    {
        $sql = <<<SQL
            SELECT `name`, `ttl` FROM `{$this->prefixTableName}prometheus_metadata`
            WHERE `type` = 'summary';
            SQL;

        $rows = $this->database->selectAll($sql);
        foreach ($rows as $row) {
            $sql = <<<SQL
            DELETE FROM `{$this->prefixTableName}prometheus_summaries`
            WHERE `name` = :name AND (:time - `time`) > :ttl
            SQL;

            $this->database->selectAll($sql, [
                'name' => $row['name'],
                'ttl'  => $row['ttl'],
                'time' => $this->time()
            ]);
        }
    }

    // endregion

    // region Wipe storage

    /** @throws DatabaseException */
    public function wipeStorage(): void
    {
        $tables = [
            $this->prefixTableName . 'prometheus_metadata',
            $this->prefixTableName . 'prometheus_values',
            $this->prefixTableName . 'prometheus_histograms',
            $this->prefixTableName . 'prometheus_summaries'
        ];

        $this->database->truncateTables(...$tables);
    }

    /**
     * Drop all tables.
     *
     * @throws DatabaseException
     */
    public function deleteStorage(): void
    {
        $tables = [
            $this->prefixTableName . 'prometheus_metadata',
            $this->prefixTableName . 'prometheus_values',
            $this->prefixTableName . 'prometheus_histograms',
            $this->prefixTableName . 'prometheus_summaries'
        ];

        $this->database->dropTables(...$tables);
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
