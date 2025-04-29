<?php

declare(strict_types=1);

namespace tests\InMemory;

use PHPUnit\Framework\TestCase;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\StorageException;
use Rancoud\Prometheus\Storage\InMemory;
use Rancoud\Prometheus\Storage\Operation;

/** @internal */
class InMemoryTest extends TestCase
{
    /**
     * @throws \JsonException
     * @throws StorageException
     */
    public function testCollect(): void
    {
        $storage = new InMemory();

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

    /** @throws StorageException */
    public function testCollectInvalidMetricException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Invalid metric 'foo': it is not supported.");

        $iterator = new InMemory()->collect('foo', 'bar');
        /* @noinspection PhpPossiblePolymorphicInvocationInspection */
        $iterator->current();
    }

    /**
     * @throws \JsonException
     * @throws StorageException
     *
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function testCollectEmpty(): void
    {
        $storage = new InMemory();
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
     * @throws StorageException
     */
    public function testExpose(): void
    {
        $storage = new InMemory();

        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        $exposedArray = $storage->expose();

        $output = '';
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
     * @throws StorageException
     */
    public function testExposeInvalidMetricException(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Invalid metric 'foo': it is not supported.");

        $iterator = new InMemory()->expose('foo', 'bar');
        /* @noinspection PhpPossiblePolymorphicInvocationInspection */
        $iterator->current();
    }

    /**
     * @throws \JsonException
     * @throws StorageException
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function testExposeEmpty(): void
    {
        $storage = new InMemory();
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

    /** @throws \JsonException */
    public function testWipeStorage(): void
    {
        $storage = new InMemory();

        $reflection = new \ReflectionClass($storage);

        static::assertEmpty($reflection->getProperty('counters')->getValue($storage));
        static::assertEmpty($reflection->getProperty('gauges')->getValue($storage));
        static::assertEmpty($reflection->getProperty('histograms')->getValue($storage));
        static::assertEmpty($reflection->getProperty('summaries')->getValue($storage));

        $storage->updateCounter(new Descriptor('my_counter'));
        $storage->updateGauge(new Descriptor('my_gauge'), Operation::Add);
        $storage->updateHistogram(new Descriptor('my_histogram'), 1.0);
        $storage->updateSummary(new Descriptor('my_summary'), 1.0);

        static::assertNotEmpty($reflection->getProperty('counters')->getValue($storage));
        static::assertNotEmpty($reflection->getProperty('gauges')->getValue($storage));
        static::assertNotEmpty($reflection->getProperty('histograms')->getValue($storage));
        static::assertNotEmpty($reflection->getProperty('summaries')->getValue($storage));

        $storage->wipeStorage();

        static::assertEmpty($reflection->getProperty('counters')->getValue($storage));
        static::assertEmpty($reflection->getProperty('gauges')->getValue($storage));
        static::assertEmpty($reflection->getProperty('histograms')->getValue($storage));
        static::assertEmpty($reflection->getProperty('summaries')->getValue($storage));
    }
}
