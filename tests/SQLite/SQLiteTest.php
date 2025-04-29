<?php

declare(strict_types=1);

namespace tests\SQLite;

use PHPUnit\Framework\TestCase;
use Rancoud\Database\Configurator;
use Rancoud\Database\Database;
use Rancoud\Database\DatabaseException;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\DescriptorException;
use Rancoud\Prometheus\Exception\StorageException;
use Rancoud\Prometheus\Storage\Operation;
use Rancoud\Prometheus\Storage\SQLite;

/** @internal */
class SQLiteTest extends TestCase
{
    /**
     * @throws DatabaseException
     * @throws StorageException
     */
    protected function newSQLiteMemory(): SQLite
    {
        $params = [
            'driver'    => 'sqlite',
            'host'      => ':memory',
            'user'      => '',
            'password'  => '',
            'database'  => ''
        ];

        return new SQLite(new Database(new Configurator($params)));
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws StorageException
     */
    public function testConstructPrefixTable(): void
    {
        $params = [
            'driver'    => 'sqlite',
            'host'      => ':memory',
            'user'      => '',
            'password'  => '',
            'database'  => ''
        ];

        $database = new Database(new Configurator($params));

        $storage = new SQLite($database, 'my_prefix');

        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_metadata`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_values`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_histograms`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_summaries`'));

        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        static::assertNotEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_metadata`'));
        static::assertNotEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_values`'));
        static::assertNotEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_histograms`'));
        static::assertNotEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_summaries`'));

        $storage->wipeStorage();

        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_metadata`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_values`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_histograms`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `my_prefix_prometheus_summaries`'));
    }

    /**
     * @throws DatabaseException
     * @throws StorageException
     */
    public function testConstructExceptionOnPrefixTable(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Invalid prefix name 'my prefix': it need to follow that pattern [a-zA-Z0-9_-]");

        $params = [
            'driver'    => 'sqlite',
            'host'      => ':memory',
            'user'      => '',
            'password'  => '',
            'database'  => ''
        ];

        $database = new Database(new Configurator($params));

        new SQLite($database, 'my prefix');
    }

    /**
     * @throws DatabaseException
     * @throws StorageException
     */
    public function testConstructSkipCreateTables(): void
    {
        $params = [
            'driver'    => 'sqlite',
            'host'      => ':memory',
            'user'      => '',
            'password'  => '',
            'database'  => ''
        ];

        $database = new Database(new Configurator($params));
        $database->enableSaveQueries();

        new SQLite($database, '', false);

        static::assertEmpty($database->getSavedQueries());

        $database = new Database(new Configurator($params));
        $database->enableSaveQueries();

        new SQLite($database, '', true);

        $savedQueries = $database->getSavedQueries();
        static::assertNotEmpty($savedQueries);

        /*
         * 6 queries:
         * - connect
         * - create table prometheus_metadata
         * - create table prometheus_values
         * - create table prometheus_histograms
         * - create table prometheus_summaries
         * - create index for prometheus_summaries
         */
        static::assertCount(6, $savedQueries);
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    public function testCollect(): void
    {
        $storage = $this->newSQLiteMemory();

        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        $collectArray = $storage->collect();
        $metricName = [4 => 'my_counter', 3 => 'my_gauge', 2 => 'my_histogram', 1 => 'my_summary'];
        $countMetric = 4;

        foreach ($collectArray as $collector) {
            static::assertIsArray($collector);
            static::assertArrayHasKey('descriptor', $collector);
            static::assertArrayHasKey('samples', $collector);
            static::assertSame($metricName[$countMetric], $collector['descriptor']->name());
            --$countMetric;
        }
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    public function testCollectInvalidMetricException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Invalid metric 'foo': it is not supported.");

        $iterator = $this->newSQLiteMemory()->collect('foo', 'bar');
        /* @noinspection PhpPossiblePolymorphicInvocationInspection */
        $iterator->current();
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function testCollectEmpty(): void
    {
        $storage = $this->newSQLiteMemory();
        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        $iterator = $storage->collect('counter', 'bar');
        static::assertNull($iterator->current());

        $iterator = $storage->collect('gauge', 'bar');
        static::assertNull($iterator->current());

        $iterator = $storage->collect('histogram', 'bar');
        static::assertNull($iterator->current());

        $iterator = $storage->collect('summary', 'bar');
        static::assertNull($iterator->current());
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    public function testExpose(): void
    {
        $storage = $this->newSQLiteMemory();

        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        $exposedArray = $storage->expose();

        $output = '';
        /* @noinspection PhpLoopCanBeReplacedWithImplodeInspection */
        foreach ($exposedArray as $exposeString) {
            $output .= $exposeString;
        }

        $expected = <<<'PLAINTEXT'
            #TYPE my_counter counter
            my_counter 1
            #TYPE my_gauge gauge
            my_gauge 1
            #TYPE my_histogram histogram
            my_histogram_bucket{le="0.005"} 0
            my_histogram_bucket{le="0.01"} 0
            my_histogram_bucket{le="0.025"} 0
            my_histogram_bucket{le="0.05"} 0
            my_histogram_bucket{le="0.075"} 0
            my_histogram_bucket{le="0.1"} 0
            my_histogram_bucket{le="0.25"} 0
            my_histogram_bucket{le="0.5"} 0
            my_histogram_bucket{le="0.75"} 0
            my_histogram_bucket{le="1"} 1
            my_histogram_bucket{le="2.5"} 1
            my_histogram_bucket{le="5"} 1
            my_histogram_bucket{le="7.5"} 1
            my_histogram_bucket{le="10"} 1
            my_histogram_bucket{le="+Inf"} 1
            my_histogram_count 1
            my_histogram_sum 1
            #TYPE my_summary summary
            my_summary{quantile="0.01"} 1
            my_summary{quantile="0.05"} 1
            my_summary{quantile="0.5"} 1
            my_summary{quantile="0.95"} 1
            my_summary{quantile="0.99"} 1
            my_summary_count 1
            my_summary_sum 1

            PLAINTEXT;

        static::assertSame($expected, $output);
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     */
    public function testExposeInvalidMetricException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Invalid metric 'foo': it is not supported.");

        $iterator = $this->newSQLiteMemory()->expose('foo', 'bar');
        /* @noinspection PhpPossiblePolymorphicInvocationInspection */
        $iterator->current();
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws DescriptorException
     * @throws StorageException
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function testExposeEmpty(): void
    {
        $storage = $this->newSQLiteMemory();
        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        $iterator = $storage->expose('counter', 'bar');
        static::assertNull($iterator->current());

        $iterator = $storage->expose('gauge', 'bar');
        static::assertNull($iterator->current());

        $iterator = $storage->expose('histogram', 'bar');
        static::assertNull($iterator->current());

        $iterator = $storage->expose('summary', 'bar');
        static::assertNull($iterator->current());
    }

    /**
     * @throws \JsonException
     * @throws DatabaseException
     * @throws StorageException
     */
    public function testWipeStorage(): void
    {
        $params = [
            'driver'    => 'sqlite',
            'host'      => ':memory',
            'user'      => '',
            'password'  => '',
            'database'  => ''
        ];

        $database = new Database(new Configurator($params));

        $storage = new SQLite($database);

        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_metadata`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_values`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_histograms`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_summaries`'));

        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        static::assertNotEmpty($database->selectAll('SELECT * FROM `prometheus_metadata`'));
        static::assertNotEmpty($database->selectAll('SELECT * FROM `prometheus_values`'));
        static::assertNotEmpty($database->selectAll('SELECT * FROM `prometheus_histograms`'));
        static::assertNotEmpty($database->selectAll('SELECT * FROM `prometheus_summaries`'));

        $storage->wipeStorage();

        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_metadata`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_values`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_histograms`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_summaries`'));
    }

    /**
     * @throws DatabaseException
     * @throws StorageException
     */
    public function testDeleteStorage(): void
    {
        $params = [
            'driver'    => 'sqlite',
            'host'      => ':memory',
            'user'      => '',
            'password'  => '',
            'database'  => ''
        ];

        $database = new Database(new Configurator($params));

        $storage = new SQLite($database);

        static::assertNotEmpty($database->selectAll('SELECT * FROM `sqlite_schema`'));

        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_metadata`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_values`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_histograms`'));
        static::assertEmpty($database->selectAll('SELECT * FROM `prometheus_summaries`'));

        $storage->deleteStorage();

        static::assertEmpty($database->selectAll('SELECT * FROM `sqlite_schema`'));
    }
}
