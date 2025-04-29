<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use Rancoud\Prometheus\Counter;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Gauge;
use Rancoud\Prometheus\Histogram;
use Rancoud\Prometheus\Registry;
use Rancoud\Prometheus\Storage\Adapter;
use Rancoud\Prometheus\Summary;

/** @internal */
abstract class AbstractRegistry extends TestCase
{
    protected Adapter $storage;

    public function testRegisterAndUnregister(): void
    {
        $registry = new Registry();
        $counter1 = new Counter($this->storage, new Descriptor('my_counter_1'));
        $counter2 = new Counter($this->storage, new Descriptor('my_counter_2'));

        $registry->register($counter1, $counter2);

        $reflection = new \ReflectionClass($registry);
        $reflectionProperty = $reflection->getProperty('collectors');
        $collectorsRegistered = $reflectionProperty->getValue($registry);

        $expected = [
            'my_counter_1' => $counter1,
            'my_counter_2' => $counter2
        ];

        static::assertSame($expected, $collectorsRegistered);

        $registry->unregister($counter1);

        $collectorsRegistered = $reflectionProperty->getValue($registry);

        $expected = [
            'my_counter_2' => $counter2
        ];

        static::assertSame($expected, $collectorsRegistered);
    }

    /**
     * @throws \Exception
     * @noinspection PhpParamsInspection
     */
    public function testCollectAndExpose(): void
    {
        // region Init
        $registry = new Registry();

        $collectArray = $registry->collect();

        /*
         * @noinspection PhpLoopNeverIteratesInspection
         * @noinspection PhpUnusedLocalVariableInspection
         */
        foreach ($collectArray as $collector) {
            throw new \RuntimeException('supposed to be empty');
        }

        $exposedString = $registry->expose();
        static::assertEmpty($exposedString);
        // endregion

        // region 1 metric
        $counter = new Counter($this->storage, new Descriptor('my_counter'));
        $registry->register($counter);
        $counter->inc();
        $countMetric = 1;

        $collectArray = $registry->collect();
        foreach ($collectArray as $collector) {
            static::assertIsArray($collector);
            static::assertArrayHasKey('descriptor', $collector);
            static::assertArrayHasKey('samples', $collector);
            static::assertSame('my_counter', $collector['descriptor']->name());
            --$countMetric;
        }
        static::assertEmpty($countMetric);

        $exposedString = $registry->expose();
        static::assertSame(<<<'PLAINTEXT'
            #TYPE my_counter counter
            my_counter 1

            PLAINTEXT, $exposedString);
        // endregion

        // region 2 metrics
        $gauge = new Gauge($this->storage, new Descriptor('my_gauge'));
        $registry->register($gauge);
        $gauge->dec(4);
        $countMetric = 2;
        $metricName = [2 => 'my_counter', 1 => 'my_gauge'];

        $collectArray = $registry->collect();
        foreach ($collectArray as $collector) {
            static::assertIsArray($collector);
            static::assertArrayHasKey('descriptor', $collector);
            static::assertArrayHasKey('samples', $collector);
            static::assertSame($metricName[$countMetric], $collector['descriptor']->name());
            --$countMetric;
        }
        static::assertEmpty($countMetric);

        $exposedString = $registry->expose();

        static::assertSame(<<<'PLAINTEXT'
            #TYPE my_counter counter
            my_counter 1
            #TYPE my_gauge gauge
            my_gauge -4

            PLAINTEXT, $exposedString);
        // endregion

        // region 3 metrics
        $histogram = new Histogram($this->storage, new Descriptor('my_histogram'));
        $registry->register($histogram);
        $histogram->observe(4);
        $countMetric = 3;
        $metricName = [3 => 'my_counter', 2 => 'my_gauge', 1 => 'my_histogram'];

        $collectArray = $registry->collect();
        foreach ($collectArray as $collector) {
            static::assertIsArray($collector);
            static::assertArrayHasKey('descriptor', $collector);
            static::assertArrayHasKey('samples', $collector);
            static::assertSame($metricName[$countMetric], $collector['descriptor']->name());
            --$countMetric;
        }
        static::assertEmpty($countMetric);

        $exposedString = $registry->expose();

        static::assertSame(<<<'PLAINTEXT'
            #TYPE my_counter counter
            my_counter 1
            #TYPE my_gauge gauge
            my_gauge -4
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
            my_histogram_bucket{le="1"} 0
            my_histogram_bucket{le="2.5"} 0
            my_histogram_bucket{le="5"} 1
            my_histogram_bucket{le="7.5"} 1
            my_histogram_bucket{le="10"} 1
            my_histogram_bucket{le="+Inf"} 1
            my_histogram_count 1
            my_histogram_sum 4

            PLAINTEXT, $exposedString);
        // endregion

        // region 4 metrics
        $summary = new Summary($this->storage, new Descriptor('my_summary'));
        $registry->register($summary);
        $summary->observe(4);
        $countMetric = 4;
        $metricName = [4 => 'my_counter', 3 => 'my_gauge', 2 => 'my_histogram', 1 => 'my_summary'];

        $collectArray = $registry->collect();
        foreach ($collectArray as $collector) {
            static::assertIsArray($collector);
            static::assertArrayHasKey('descriptor', $collector);
            static::assertArrayHasKey('samples', $collector);
            static::assertSame($metricName[$countMetric], $collector['descriptor']->name());
            --$countMetric;
        }
        static::assertEmpty($countMetric);

        $exposedString = $registry->expose();

        static::assertSame(<<<'PLAINTEXT'
            #TYPE my_counter counter
            my_counter 1
            #TYPE my_gauge gauge
            my_gauge -4
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
            my_histogram_bucket{le="1"} 0
            my_histogram_bucket{le="2.5"} 0
            my_histogram_bucket{le="5"} 1
            my_histogram_bucket{le="7.5"} 1
            my_histogram_bucket{le="10"} 1
            my_histogram_bucket{le="+Inf"} 1
            my_histogram_count 1
            my_histogram_sum 4
            #TYPE my_summary summary
            my_summary{quantile="0.01"} 4
            my_summary{quantile="0.05"} 4
            my_summary{quantile="0.5"} 4
            my_summary{quantile="0.95"} 4
            my_summary{quantile="0.99"} 4
            my_summary_count 1
            my_summary_sum 4

            PLAINTEXT, $exposedString);
        // endregion
    }

    /** @throws CollectorException */
    public function testDefaultRegistry(): void
    {
        $counter1 = new Counter($this->storage, new Descriptor('my_counter_1'));

        Registry::registerInDefault($counter1);

        $counter1->inc(34);

        new Counter($this->storage, new Descriptor('my_counter_2'))->register()->inc(43);

        $exposedString = Registry::getDefault()->expose();

        static::assertSame(<<<'PLAINTEXT'
            #TYPE my_counter_1 counter
            my_counter_1 34
            #TYPE my_counter_2 counter
            my_counter_2 43

            PLAINTEXT, $exposedString);
    }
}
