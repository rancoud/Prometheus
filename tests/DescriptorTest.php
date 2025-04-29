<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\DescriptorException;

/** @internal */
class DescriptorTest extends TestCase
{
    // region Constructor

    public static function provideConstructorDataCases(): iterable
    {
        yield 'OK' => [
            'name'   => 'my_metric',
            'labels' => ['my_label_1', 'my_label_2'],
            'error'  => null
        ];

        yield 'KO - Invalid name, regex case' => [
            'name'   => 'my_metric foo',
            'labels' => ['my_label_1', 'my_label_2'],
            'error'  => "Invalid metric name 'my_metric foo': it need to follow that pattern [a-zA-Z_:][a-zA-Z0-9_:]"
        ];

        yield 'KO - Invalid label, __ case' => [
            'name'   => 'my_metric',
            'labels' => ['__my_label_1', 'my_label_2'],
            'error'  => "Invalid label name '__my_label_1': it can't have a label name that starts with '__'"
        ];

        yield 'KO - Invalid label, regex case' => [
            'name'   => 'my_metric',
            'labels' => ['my_label_1', 'my_label_2 foo'],
            'error'  => "Invalid label name 'my_label_2 foo': it need to follow that pattern [a-zA-Z_][a-zA-Z0-9_]"
        ];
    }

    /** @throws DescriptorException */
    #[DataProvider('provideConstructorDataCases')]
    public function testConstructor(string $name, array $labels, ?string $error): void
    {
        if ($error !== null) {
            $this->expectException(DescriptorException::class);
            $this->expectExceptionMessage($error);
        }

        new Descriptor($name, $labels);

        static::assertTrue(true);
    }

    // endregion

    // region Setters

    public function testSetHelp(): void
    {
        $descriptor = new Descriptor('name');
        $descriptor->setHelp('this is a text for help');

        $reflection = new \ReflectionClass($descriptor);
        $reflectionProperty = $reflection->getProperty('help');

        static::assertSame('this is a text for help', $reflectionProperty->getValue($descriptor));
    }

    public static function provideSetHistogramBucketsDataCases(): iterable
    {
        yield 'OK - 1 bucket' => [
            'buckets' => [0],
            'error'   => null
        ];

        yield 'OK - 2 buckets' => [
            'buckets' => [0, 1],
            'error'   => null
        ];

        yield 'KO - Invalid buckets, empty bucket' => [
            'buckets' => [],
            'error'   => 'Invalid histogram buckets: it must have at least one bucket.'
        ];

        yield 'KO - Invalid bucket, bucket #0 is not a float or int' => [
            'buckets' => ['4'],
            'error'   => 'Invalid histogram bucket: at index #0 value must be a float or a int.'
        ];

        yield 'KO - Invalid bucket, bucket #1 is not a float or int' => [
            'buckets' => [0, '4', 6],
            'error'   => 'Invalid histogram bucket: at index #1 value must be a float or a int.'
        ];

        yield 'KO - Invalid bucket, bucket #3 is not a float or int' => [
            'buckets' => [0, 1, 2, '4'],
            'error'   => 'Invalid histogram bucket: at index #3 value must be a float or a int.'
        ];

        yield 'KO - Invalid bucket, buckets not in increasing order' => [
            'buckets' => [1, 0],
            'error'   => 'Invalid histogram buckets: it must be in increasing order. Failed on 1 >= 0.'
        ];
    }

    /** @throws DescriptorException */
    #[DataProvider('provideSetHistogramBucketsDataCases')]
    public function testSetHistogramBuckets(array $buckets, ?string $error): void
    {
        if ($error !== null) {
            $this->expectException(DescriptorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor('name');
        $descriptor->setHistogramBuckets($buckets);

        $reflection = new \ReflectionClass($descriptor);
        $reflectionProperty = $reflection->getProperty('buckets');

        static::assertSame($buckets, $reflectionProperty->getValue($descriptor));
    }

    public static function provideSetSummaryTTLDataCases(): iterable
    {
        yield 'OK' => [
            'ttlInSeconds' => 1,
            'error'        => null
        ];

        yield 'KO - Invalid TTL value because -1' => [
            'ttlInSeconds' => -1,
            'error'        => "Invalid TTL value '-1': it must be greater than 0."
        ];

        yield 'KO - Invalid TTL value because 0' => [
            'ttlInSeconds' => 0,
            'error'        => "Invalid TTL value '0': it must be greater than 0."
        ];
    }

    /** @throws DescriptorException */
    #[DataProvider('provideSetSummaryTTLDataCases')]
    public function testSetSummaryTTL(int $ttlInSeconds, ?string $error): void
    {
        if ($error !== null) {
            $this->expectException(DescriptorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor('name');
        $descriptor->setSummaryTTL($ttlInSeconds);

        $reflection = new \ReflectionClass($descriptor);
        $reflectionProperty = $reflection->getProperty('ttlInSeconds');

        static::assertSame($ttlInSeconds, $reflectionProperty->getValue($descriptor));
    }

    public static function provideSetSummaryQuantilesDataCases(): iterable
    {
        yield 'OK - 1 quantile' => [
            'quantiles' => [0.1],
            'error'     => null
        ];

        yield 'OK - 2 quantiles' => [
            'quantiles' => [0.1, 0.9],
            'error'     => null
        ];

        yield 'KO - Invalid quantiles, empty quantile' => [
            'quantiles' => [],
            'error'     => 'Invalid summary quantiles: it must have at least one quantile.'
        ];

        yield 'KO - Invalid quantile, quantile #0 is not a float or int' => [
            'quantiles' => ['0.5'],
            'error'     => 'Invalid summary quantile: at index #0 value must be a float or a int.'
        ];

        yield 'KO - Invalid quantile, quantile #0 is not between 0 and 1 (0)' => [
            'quantiles' => [0],
            'error'     => 'Invalid summary quantile: at index #0 value must be between 0 and 1.'
        ];

        yield 'KO - Invalid quantile, quantile #0 is not between 0 and 1 (1)' => [
            'quantiles' => [1],
            'error'     => 'Invalid summary quantile: at index #0 value must be between 0 and 1.'
        ];

        yield 'KO - Invalid quantile, quantile #1 is not a float or int' => [
            'quantiles' => [0.1, '0.5', 0.9],
            'error'     => 'Invalid summary quantile: at index #1 value must be a float or a int.'
        ];

        yield 'KO - Invalid quantile, quantile #1 is not between 0 and 1 (1)' => [
            'quantiles' => [0.1, 1, 0.9],
            'error'     => 'Invalid summary quantile: at index #1 value must be between 0 and 1.'
        ];

        yield 'KO - Invalid quantile, quantile #3 is not a float or int' => [
            'quantiles' => [0.1, 0.5, 0.7, '0.9'],
            'error'     => 'Invalid summary quantile: at index #3 value must be a float or a int.'
        ];

        yield 'KO - Invalid quantile, quantiles not in increasing order' => [
            'quantiles' => [0.9, 0.1],
            'error'     => 'Invalid summary quantiles: it must be in increasing order. Failed on 0.9 >= 0.1.'
        ];
    }

    /** @throws DescriptorException */
    #[DataProvider('provideSetSummaryQuantilesDataCases')]
    public function testSetSummaryQuantiles(array $quantiles, ?string $error): void
    {
        if ($error !== null) {
            $this->expectException(DescriptorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor('name');
        $descriptor->setSummaryQuantiles($quantiles);

        $reflection = new \ReflectionClass($descriptor);
        $reflectionProperty = $reflection->getProperty('quantiles');

        static::assertSame($quantiles, $reflectionProperty->getValue($descriptor));
    }

    // endregion

    // region Helpers

    public function testName(): void
    {
        static::assertSame('name', new Descriptor('name')->name());
    }

    /** @throws DescriptorException */
    public function testLabels(): void
    {
        static::assertSame([], new Descriptor('name')->labels());
        static::assertSame(['label_1'], new Descriptor('name', ['label_1'])->labels());
        static::assertSame(['label_1', 'label_2'], new Descriptor('name', ['label_1', 'label_2'])->labels());
    }

    /** @throws DescriptorException */
    public function testLabelsCount(): void
    {
        static::assertSame(0, new Descriptor('name')->labelsCount());
        static::assertSame(1, new Descriptor('name', ['label_1'])->labelsCount());
        static::assertSame(2, new Descriptor('name', ['label_1', 'label_2'])->labelsCount());
    }

    /** @throws DescriptorException */
    public function testBuckets(): void
    {
        $defaultBuckets = [.005, .01, .025, .05, 0.075, .1, .25, .5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0];
        static::assertSame($defaultBuckets, new Descriptor('name')->buckets());
        static::assertSame([0, 1], new Descriptor('name')->setHistogramBuckets([0, 1])->buckets());
    }

    /** @throws DescriptorException */
    public function testQuantiles(): void
    {
        $defaultQuantiles = [0.01, 0.05, 0.5, 0.95, 0.99];
        static::assertSame($defaultQuantiles, new Descriptor('name')->quantiles());
        static::assertSame([0.1, 0.9], new Descriptor('name')->setSummaryQuantiles([0.1, 0.9])->quantiles());
    }

    /** @throws DescriptorException */
    public function testTTLInSeconds(): void
    {
        static::assertSame(600, new Descriptor('name')->ttlInSeconds());
        static::assertSame(10, new Descriptor('name')->setSummaryTTL(10)->ttlInSeconds());
    }

    // endregion

    // region Expose

    public function testExportHelp(): void
    {
        $descriptor = new Descriptor('name');
        static::assertSame('', $descriptor->exportHelp());

        $descriptor->setHelp('my text for help');
        static::assertSame('#HELP name my text for help' . "\n", $descriptor->exportHelp());
    }

    public function testExportType(): void
    {
        static::assertSame('#TYPE name counter' . "\n", new Descriptor('name')->exportType('counter'));
    }

    public static function provideExportValueDataCases(): iterable
    {
        yield 'OK - 0 label' => [
            'labels'      => [],
            'value'       => 1,
            'labelValues' => [],
            'suffixName'  => '',
            'output'      => <<<'PLAINTEXT'
                name 1

                PLAINTEXT
        ];

        yield 'OK - 1 label' => [
            'labels'      => ['method'],
            'value'       => 2.1,
            'labelValues' => ['GET'],
            'suffixName'  => '',
            'output'      => <<<'PLAINTEXT'
                name{method="GET"} 2.1

                PLAINTEXT
        ];

        yield 'OK - 1 label + suffix' => [
            'labels'      => ['method'],
            'value'       => 1,
            'labelValues' => ['GET'],
            'suffixName'  => '_sum',
            'output'      => <<<'PLAINTEXT'
                name_sum{method="GET"} 1

                PLAINTEXT
        ];

        yield 'OK - 2 labels' => [
            'labels'      => ['method', 'path'],
            'value'       => 1,
            'labelValues' => ['GET', '/home'],
            'suffixName'  => '',
            'output'      => <<<'PLAINTEXT'
                name{method="GET",path="/home"} 1

                PLAINTEXT
        ];
    }

    /** @throws DescriptorException */
    #[DataProvider('provideExportValueDataCases')]
    public function testExportValue(array $labels, float|int $value, array $labelValues, string $suffixName, string $output): void
    {
        $descriptor = new Descriptor('name', $labels);
        $exportValue = $descriptor->exportValue($value, $labelValues, $suffixName);

        static::assertSame($output, $exportValue);
    }

    public static function provideExportHistogramValueDataCases(): iterable
    {
        yield 'OK - 0 label' => [
            'labels'      => [],
            'bucket'      => '.25',
            'value'       => 1,
            'labelValues' => [],
            'output'      => <<<'PLAINTEXT'
                name_bucket{le=".25"} 1

                PLAINTEXT
        ];

        yield 'OK - 1 label' => [
            'labels'      => ['method'],
            'bucket'      => '.25',
            'value'       => 1,
            'labelValues' => ['GET'],
            'output'      => <<<'PLAINTEXT'
                name_bucket{method="GET",le=".25"} 1

                PLAINTEXT
        ];

        yield 'OK - 2 labels' => [
            'labels'      => ['method', 'path'],
            'bucket'      => '.25',
            'value'       => 1,
            'labelValues' => ['GET', '/home'],
            'output'      => <<<'PLAINTEXT'
                name_bucket{method="GET",path="/home",le=".25"} 1

                PLAINTEXT
        ];
    }

    /** @throws DescriptorException */
    #[DataProvider('provideExportHistogramValueDataCases')]
    public function testExportHistogramValue(array $labels, string $bucket, int $value, array $labelValues, string $output): void
    {
        $descriptor = new Descriptor('name', $labels);
        $exportValue = $descriptor->exportHistogramValue($bucket, $value, $labelValues);

        static::assertSame($output, $exportValue);
    }

    public static function provideExportSummaryValueDataCases(): iterable
    {
        yield 'OK - 0 label' => [
            'labels'      => [],
            'quantile'    => 0.5,
            'values'      => [1],
            'labelValues' => [],
            'output'      => <<<'PLAINTEXT'
                name{quantile="0.5"} 1

                PLAINTEXT
        ];

        yield 'OK - 1 label' => [
            'labels'      => ['method'],
            'quantile'    => 0.5,
            'values'      => [1],
            'labelValues' => ['GET'],
            'output'      => <<<'PLAINTEXT'
                name{method="GET",quantile="0.5"} 1

                PLAINTEXT
        ];

        yield 'OK - 2 labels' => [
            'labels'      => ['method', 'path'],
            'quantile'    => 0.5,
            'values'      => [1],
            'labelValues' => ['GET', '/home'],
            'output'      => <<<'PLAINTEXT'
                name{method="GET",path="/home",quantile="0.5"} 1

                PLAINTEXT
        ];
    }

    /** @throws DescriptorException */
    #[DataProvider('provideExportSummaryValueDataCases')]
    public function testExportSummaryValue(array $labels, float $quantile, array $values, array $labelValues, string $output): void
    {
        $descriptor = new Descriptor('name', $labels);
        $exportValue = $descriptor->exportSummaryValue($quantile, $values, $labelValues);

        static::assertSame($output, $exportValue);
    }

    // endregion
}
