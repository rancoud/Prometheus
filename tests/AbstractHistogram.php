<?php

/** @noinspection PhpTooManyParametersInspection */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Exception\DescriptorException;
use Rancoud\Prometheus\Histogram;
use Rancoud\Prometheus\Storage\Adapter;

/** @internal */
abstract class AbstractHistogram extends TestCase
{
    protected Adapter $storage;

    public static function provideObserveDataCases(): iterable
    {
        yield 'OK - 2 labels + help' => [
            'name'          => 'my_metric',
            'labels'        => ['method', 'path'],
            'help'          => 'description here',
            'buckets'       => null,
            'values'        => [1, 3, 3, 5],
            'labelValues'   => [['GET', 'login'], ['GET', 'login'], ['POST', 'contact'], ['PUT', '‍💻金\"\n\\']],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric histogram
                my_metric_bucket{method="GET",path="login",le="0.005"} 0
                my_metric_bucket{method="GET",path="login",le="0.01"} 0
                my_metric_bucket{method="GET",path="login",le="0.025"} 0
                my_metric_bucket{method="GET",path="login",le="0.05"} 0
                my_metric_bucket{method="GET",path="login",le="0.075"} 0
                my_metric_bucket{method="GET",path="login",le="0.1"} 0
                my_metric_bucket{method="GET",path="login",le="0.25"} 0
                my_metric_bucket{method="GET",path="login",le="0.5"} 0
                my_metric_bucket{method="GET",path="login",le="0.75"} 0
                my_metric_bucket{method="GET",path="login",le="1"} 1
                my_metric_bucket{method="GET",path="login",le="2.5"} 1
                my_metric_bucket{method="GET",path="login",le="5"} 1
                my_metric_bucket{method="GET",path="login",le="7.5"} 1
                my_metric_bucket{method="GET",path="login",le="10"} 1
                my_metric_bucket{method="GET",path="login",le="+Inf"} 1
                my_metric_count{method="GET",path="login"} 1
                my_metric_sum{method="GET",path="login"} 1

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric histogram
                my_metric_bucket{method="GET",path="login",le="0.005"} 0
                my_metric_bucket{method="GET",path="login",le="0.01"} 0
                my_metric_bucket{method="GET",path="login",le="0.025"} 0
                my_metric_bucket{method="GET",path="login",le="0.05"} 0
                my_metric_bucket{method="GET",path="login",le="0.075"} 0
                my_metric_bucket{method="GET",path="login",le="0.1"} 0
                my_metric_bucket{method="GET",path="login",le="0.25"} 0
                my_metric_bucket{method="GET",path="login",le="0.5"} 0
                my_metric_bucket{method="GET",path="login",le="0.75"} 0
                my_metric_bucket{method="GET",path="login",le="1"} 1
                my_metric_bucket{method="GET",path="login",le="2.5"} 1
                my_metric_bucket{method="GET",path="login",le="5"} 2
                my_metric_bucket{method="GET",path="login",le="7.5"} 2
                my_metric_bucket{method="GET",path="login",le="10"} 2
                my_metric_bucket{method="GET",path="login",le="+Inf"} 2
                my_metric_count{method="GET",path="login"} 2
                my_metric_sum{method="GET",path="login"} 4

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric histogram
                my_metric_bucket{method="GET",path="login",le="0.005"} 0
                my_metric_bucket{method="GET",path="login",le="0.01"} 0
                my_metric_bucket{method="GET",path="login",le="0.025"} 0
                my_metric_bucket{method="GET",path="login",le="0.05"} 0
                my_metric_bucket{method="GET",path="login",le="0.075"} 0
                my_metric_bucket{method="GET",path="login",le="0.1"} 0
                my_metric_bucket{method="GET",path="login",le="0.25"} 0
                my_metric_bucket{method="GET",path="login",le="0.5"} 0
                my_metric_bucket{method="GET",path="login",le="0.75"} 0
                my_metric_bucket{method="GET",path="login",le="1"} 1
                my_metric_bucket{method="GET",path="login",le="2.5"} 1
                my_metric_bucket{method="GET",path="login",le="5"} 2
                my_metric_bucket{method="GET",path="login",le="7.5"} 2
                my_metric_bucket{method="GET",path="login",le="10"} 2
                my_metric_bucket{method="GET",path="login",le="+Inf"} 2
                my_metric_count{method="GET",path="login"} 2
                my_metric_sum{method="GET",path="login"} 4
                my_metric_bucket{method="POST",path="contact",le="0.005"} 0
                my_metric_bucket{method="POST",path="contact",le="0.01"} 0
                my_metric_bucket{method="POST",path="contact",le="0.025"} 0
                my_metric_bucket{method="POST",path="contact",le="0.05"} 0
                my_metric_bucket{method="POST",path="contact",le="0.075"} 0
                my_metric_bucket{method="POST",path="contact",le="0.1"} 0
                my_metric_bucket{method="POST",path="contact",le="0.25"} 0
                my_metric_bucket{method="POST",path="contact",le="0.5"} 0
                my_metric_bucket{method="POST",path="contact",le="0.75"} 0
                my_metric_bucket{method="POST",path="contact",le="1"} 0
                my_metric_bucket{method="POST",path="contact",le="2.5"} 0
                my_metric_bucket{method="POST",path="contact",le="5"} 1
                my_metric_bucket{method="POST",path="contact",le="7.5"} 1
                my_metric_bucket{method="POST",path="contact",le="10"} 1
                my_metric_bucket{method="POST",path="contact",le="+Inf"} 1
                my_metric_count{method="POST",path="contact"} 1
                my_metric_sum{method="POST",path="contact"} 3

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric histogram
                my_metric_bucket{method="GET",path="login",le="0.005"} 0
                my_metric_bucket{method="GET",path="login",le="0.01"} 0
                my_metric_bucket{method="GET",path="login",le="0.025"} 0
                my_metric_bucket{method="GET",path="login",le="0.05"} 0
                my_metric_bucket{method="GET",path="login",le="0.075"} 0
                my_metric_bucket{method="GET",path="login",le="0.1"} 0
                my_metric_bucket{method="GET",path="login",le="0.25"} 0
                my_metric_bucket{method="GET",path="login",le="0.5"} 0
                my_metric_bucket{method="GET",path="login",le="0.75"} 0
                my_metric_bucket{method="GET",path="login",le="1"} 1
                my_metric_bucket{method="GET",path="login",le="2.5"} 1
                my_metric_bucket{method="GET",path="login",le="5"} 2
                my_metric_bucket{method="GET",path="login",le="7.5"} 2
                my_metric_bucket{method="GET",path="login",le="10"} 2
                my_metric_bucket{method="GET",path="login",le="+Inf"} 2
                my_metric_count{method="GET",path="login"} 2
                my_metric_sum{method="GET",path="login"} 4
                my_metric_bucket{method="POST",path="contact",le="0.005"} 0
                my_metric_bucket{method="POST",path="contact",le="0.01"} 0
                my_metric_bucket{method="POST",path="contact",le="0.025"} 0
                my_metric_bucket{method="POST",path="contact",le="0.05"} 0
                my_metric_bucket{method="POST",path="contact",le="0.075"} 0
                my_metric_bucket{method="POST",path="contact",le="0.1"} 0
                my_metric_bucket{method="POST",path="contact",le="0.25"} 0
                my_metric_bucket{method="POST",path="contact",le="0.5"} 0
                my_metric_bucket{method="POST",path="contact",le="0.75"} 0
                my_metric_bucket{method="POST",path="contact",le="1"} 0
                my_metric_bucket{method="POST",path="contact",le="2.5"} 0
                my_metric_bucket{method="POST",path="contact",le="5"} 1
                my_metric_bucket{method="POST",path="contact",le="7.5"} 1
                my_metric_bucket{method="POST",path="contact",le="10"} 1
                my_metric_bucket{method="POST",path="contact",le="+Inf"} 1
                my_metric_count{method="POST",path="contact"} 1
                my_metric_sum{method="POST",path="contact"} 3
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.005"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.01"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.025"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.05"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.075"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.1"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.25"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.5"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="0.75"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="1"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="2.5"} 0
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="5"} 1
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="7.5"} 1
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="10"} 1
                my_metric_bucket{method="PUT",path="‍💻金\\\"\\n\\",le="+Inf"} 1
                my_metric_count{method="PUT",path="‍💻金\\\"\\n\\"} 1
                my_metric_sum{method="PUT",path="‍💻金\\\"\\n\\"} 5

                PLAINTEXT
            ]
        ];

        yield 'OK - 0 label' => [
            'name'          => 'my_metric',
            'labels'        => [],
            'help'          => '',
            'buckets'       => null,
            'values'        => [1, 3],
            'labelValues'   => [[], []],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #TYPE my_metric histogram
                my_metric_bucket{le="0.005"} 0
                my_metric_bucket{le="0.01"} 0
                my_metric_bucket{le="0.025"} 0
                my_metric_bucket{le="0.05"} 0
                my_metric_bucket{le="0.075"} 0
                my_metric_bucket{le="0.1"} 0
                my_metric_bucket{le="0.25"} 0
                my_metric_bucket{le="0.5"} 0
                my_metric_bucket{le="0.75"} 0
                my_metric_bucket{le="1"} 1
                my_metric_bucket{le="2.5"} 1
                my_metric_bucket{le="5"} 1
                my_metric_bucket{le="7.5"} 1
                my_metric_bucket{le="10"} 1
                my_metric_bucket{le="+Inf"} 1
                my_metric_count 1
                my_metric_sum 1

                PLAINTEXT,
                <<<'PLAINTEXT'
                #TYPE my_metric histogram
                my_metric_bucket{le="0.005"} 0
                my_metric_bucket{le="0.01"} 0
                my_metric_bucket{le="0.025"} 0
                my_metric_bucket{le="0.05"} 0
                my_metric_bucket{le="0.075"} 0
                my_metric_bucket{le="0.1"} 0
                my_metric_bucket{le="0.25"} 0
                my_metric_bucket{le="0.5"} 0
                my_metric_bucket{le="0.75"} 0
                my_metric_bucket{le="1"} 1
                my_metric_bucket{le="2.5"} 1
                my_metric_bucket{le="5"} 2
                my_metric_bucket{le="7.5"} 2
                my_metric_bucket{le="10"} 2
                my_metric_bucket{le="+Inf"} 2
                my_metric_count 2
                my_metric_sum 4

                PLAINTEXT
            ]
        ];

        yield 'OK - 0 label + custom buckets' => [
            'name'          => 'my_metric',
            'labels'        => [],
            'help'          => '',
            'buckets'       => [0, 5, 10],
            'values'        => [1, 7],
            'labelValues'   => [[], []],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #TYPE my_metric histogram
                my_metric_bucket{le="0"} 0
                my_metric_bucket{le="5"} 1
                my_metric_bucket{le="10"} 1
                my_metric_bucket{le="+Inf"} 1
                my_metric_count 1
                my_metric_sum 1

                PLAINTEXT,
                <<<'PLAINTEXT'
                #TYPE my_metric histogram
                my_metric_bucket{le="0"} 0
                my_metric_bucket{le="5"} 1
                my_metric_bucket{le="10"} 2
                my_metric_bucket{le="+Inf"} 2
                my_metric_count 2
                my_metric_sum 8

                PLAINTEXT
            ]
        ];

        yield 'KO - Labels values not matching labels' => [
            'name'          => 'my_metric',
            'labels'        => ['method'],
            'help'          => '',
            'buckets'       => null,
            'values'        => [1],
            'labelValues'   => [[]],
            'error'         => "Invalid labels: count labels given '0' are not matching the count labels defined '1'",
            'plaintexts'    => [[]]
        ];

        yield "KO - Label 'le' is reserved" => [
            'name'          => 'my_metric',
            'labels'        => ['le'],
            'help'          => '',
            'buckets'       => null,
            'values'        => [1],
            'labelValues'   => [[]],
            'error'         => "Invalid label name: histogram label 'le' is reserved.",
            'plaintexts'    => [[]]
        ];
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    #[DataProvider('provideObserveDataCases')]
    public function testObserve(string $name, array $labels, string $help, ?array $buckets, array $values, array $labelValues, ?string $error, array $plaintexts): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor($name, $labels);

        if ($help !== '') {
            $descriptor->setHelp($help);
        }

        if ($buckets !== null) {
            $descriptor->setHistogramBuckets($buckets);
        }

        $histogram = new Histogram($this->storage, $descriptor);

        $count = \count($plaintexts);
        for ($idx = 0; $idx < $count; ++$idx) {
            $histogram->observe($values[$idx], $labelValues[$idx]);

            static::assertSame($plaintexts[$idx], $histogram->expose());
        }
    }

    public static function provideLinearBucketsDataCases(): iterable
    {
        yield 'OK - 1 bucket' => [
            'start'        => 1,
            'width'        => 1,
            'countBuckets' => 1,
            'error'        => null,
            'buckets'      => [1.0]
        ];

        yield 'OK - 3 buckets' => [
            'start'        => 0.10,
            'width'        => 0.21,
            'countBuckets' => 3,
            'error'        => null,
            'buckets'      => [0.1, 0.31, 0.52]
        ];

        yield 'KO - Invalid start, must be equal or greater than 0' => [
            'start'        => -1,
            'width'        => 1,
            'countBuckets' => 1,
            'error'        => "Invalid argument 'start': it must be equal or greater than 0.",
            'buckets'      => []
        ];

        yield 'KO - Invalid width, must be greater than 0' => [
            'start'        => 1,
            'width'        => 0,
            'countBuckets' => 1,
            'error'        => "Invalid argument 'width': it must be greater than 0.",
            'buckets'      => []
        ];

        yield 'KO - Invalid countBuckets, must be greater than 0' => [
            'start'        => 1,
            'width'        => 1,
            'countBuckets' => 0,
            'error'        => "Invalid argument 'countBuckets': it must be greater than 0.",
            'buckets'      => []
        ];
    }

    /** @throws CollectorException */
    #[DataProvider('provideLinearBucketsDataCases')]
    public function testLinearBuckets(float $start, float $width, int $countBuckets, ?string $error, array $buckets): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $output = Histogram::linearBuckets($start, $width, $countBuckets);

        static::assertSame($buckets, $output);
    }

    public static function provideExponentialBucketsDataCases(): iterable
    {
        yield 'OK - 1 bucket' => [
            'start'        => 1,
            'growthFactor' => 2,
            'countBuckets' => 1,
            'error'        => null,
            'buckets'      => [1.0]
        ];

        yield 'OK - 3 buckets' => [
            'start'        => 0.10,
            'growthFactor' => 2.5,
            'countBuckets' => 3,
            'error'        => null,
            'buckets'      => [0.1, 0.25, 0.625]
        ];

        yield 'KO - Invalid start, must be greater than 0' => [
            'start'        => 0,
            'growthFactor' => 2,
            'countBuckets' => 1,
            'error'        => "Invalid argument 'start': it must be greater than 0.",
            'buckets'      => []
        ];

        yield 'KO - Invalid growthFactor, must be greater than 1' => [
            'start'        => 1,
            'growthFactor' => 1,
            'countBuckets' => 1,
            'error'        => "Invalid argument 'growthFactor': it must be greater than 1.",
            'buckets'      => []
        ];

        yield 'KO - Invalid countBuckets, must be greater than 0' => [
            'start'        => 1,
            'growthFactor' => 2,
            'countBuckets' => 0,
            'error'        => "Invalid argument 'countBuckets': it must be greater than 0.",
            'buckets'      => []
        ];
    }

    /** @throws CollectorException */
    #[DataProvider('provideExponentialBucketsDataCases')]
    public function testExponentialBuckets(float $start, float $growthFactor, int $countBuckets, ?string $error, array $buckets): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $output = Histogram::exponentialBuckets($start, $growthFactor, $countBuckets);

        static::assertSame($buckets, $output);
    }

    /** @throws CollectorException */
    public function testExposeAfterDeclaration(): void
    {
        $descriptor = new Descriptor('my_other_metric');

        $histogram = new Histogram($this->storage, $descriptor);

        static::assertSame(<<<'PLAINTEXT'

            PLAINTEXT, $histogram->expose());
    }
}
