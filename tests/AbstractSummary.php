<?php

/** @noinspection PhpTooManyParametersInspection */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Exception\DescriptorException;
use Rancoud\Prometheus\Storage\Adapter;
use Rancoud\Prometheus\Summary;

/** @internal */
abstract class AbstractSummary extends TestCase
{
    protected Adapter $storage;

    public static function provideObserveDataCases(): iterable
    {
        yield 'OK - 2 labels + help' => [
            'name'           => 'my_metric',
            'labels'         => ['method', 'path'],
            'help'           => 'description here',
            'quantiles'      => null,
            'values'         => [0.15, 0.56, 0.15, 0.62],
            'labelValues'    => [['GET', 'login'], ['GET', 'login'], ['POST', 'contact'], ['PUT', '💻金\"\n\\']],
            'error'          => null,
            'plaintexts'     => [<<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric summary
                my_metric{method="GET",path="login",quantile="0.01"} 0.15
                my_metric{method="GET",path="login",quantile="0.05"} 0.15
                my_metric{method="GET",path="login",quantile="0.5"} 0.15
                my_metric{method="GET",path="login",quantile="0.95"} 0.15
                my_metric{method="GET",path="login",quantile="0.99"} 0.15
                my_metric_count{method="GET",path="login"} 1
                my_metric_sum{method="GET",path="login"} 0.15

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric summary
                my_metric{method="GET",path="login",quantile="0.01"} 0.15
                my_metric{method="GET",path="login",quantile="0.05"} 0.15
                my_metric{method="GET",path="login",quantile="0.5"} 0.15
                my_metric{method="GET",path="login",quantile="0.95"} 0.56
                my_metric{method="GET",path="login",quantile="0.99"} 0.56
                my_metric_count{method="GET",path="login"} 2
                my_metric_sum{method="GET",path="login"} 0.71

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric summary
                my_metric{method="GET",path="login",quantile="0.01"} 0.15
                my_metric{method="GET",path="login",quantile="0.05"} 0.15
                my_metric{method="GET",path="login",quantile="0.5"} 0.15
                my_metric{method="GET",path="login",quantile="0.95"} 0.56
                my_metric{method="GET",path="login",quantile="0.99"} 0.56
                my_metric_count{method="GET",path="login"} 2
                my_metric_sum{method="GET",path="login"} 0.71
                my_metric{method="POST",path="contact",quantile="0.01"} 0.15
                my_metric{method="POST",path="contact",quantile="0.05"} 0.15
                my_metric{method="POST",path="contact",quantile="0.5"} 0.15
                my_metric{method="POST",path="contact",quantile="0.95"} 0.15
                my_metric{method="POST",path="contact",quantile="0.99"} 0.15
                my_metric_count{method="POST",path="contact"} 1
                my_metric_sum{method="POST",path="contact"} 0.15

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric summary
                my_metric{method="GET",path="login",quantile="0.01"} 0.15
                my_metric{method="GET",path="login",quantile="0.05"} 0.15
                my_metric{method="GET",path="login",quantile="0.5"} 0.15
                my_metric{method="GET",path="login",quantile="0.95"} 0.56
                my_metric{method="GET",path="login",quantile="0.99"} 0.56
                my_metric_count{method="GET",path="login"} 2
                my_metric_sum{method="GET",path="login"} 0.71
                my_metric{method="POST",path="contact",quantile="0.01"} 0.15
                my_metric{method="POST",path="contact",quantile="0.05"} 0.15
                my_metric{method="POST",path="contact",quantile="0.5"} 0.15
                my_metric{method="POST",path="contact",quantile="0.95"} 0.15
                my_metric{method="POST",path="contact",quantile="0.99"} 0.15
                my_metric_count{method="POST",path="contact"} 1
                my_metric_sum{method="POST",path="contact"} 0.15
                my_metric{method="PUT",path="💻金\\\"\\n\\",quantile="0.01"} 0.62
                my_metric{method="PUT",path="💻金\\\"\\n\\",quantile="0.05"} 0.62
                my_metric{method="PUT",path="💻金\\\"\\n\\",quantile="0.5"} 0.62
                my_metric{method="PUT",path="💻金\\\"\\n\\",quantile="0.95"} 0.62
                my_metric{method="PUT",path="💻金\\\"\\n\\",quantile="0.99"} 0.62
                my_metric_count{method="PUT",path="💻金\\\"\\n\\"} 1
                my_metric_sum{method="PUT",path="💻金\\\"\\n\\"} 0.62

                PLAINTEXT
            ]
        ];

        yield 'OK - 0 label' => [
            'name'           => 'my_metric',
            'labels'         => [],
            'help'           => '',
            'quantiles'      => null,
            'values'         => [0.15, 0.56],
            'labelValues'    => [[], []],
            'error'          => null,
            'plaintexts'     => [<<<'PLAINTEXT'
                #TYPE my_metric summary
                my_metric{quantile="0.01"} 0.15
                my_metric{quantile="0.05"} 0.15
                my_metric{quantile="0.5"} 0.15
                my_metric{quantile="0.95"} 0.15
                my_metric{quantile="0.99"} 0.15
                my_metric_count 1
                my_metric_sum 0.15

                PLAINTEXT,
                <<<'PLAINTEXT'
                #TYPE my_metric summary
                my_metric{quantile="0.01"} 0.15
                my_metric{quantile="0.05"} 0.15
                my_metric{quantile="0.5"} 0.15
                my_metric{quantile="0.95"} 0.56
                my_metric{quantile="0.99"} 0.56
                my_metric_count 2
                my_metric_sum 0.71

                PLAINTEXT
            ]
        ];

        yield 'OK - 0 label + custom quantiles' => [
            'name'           => 'my_metric',
            'labels'         => [],
            'help'           => '',
            'quantiles'      => [0.1, 0.5, 0.9],
            'values'         => [0.15, 0.56],
            'labelValues'    => [[], []],
            'error'          => null,
            'plaintexts'     => [<<<'PLAINTEXT'
                #TYPE my_metric summary
                my_metric{quantile="0.1"} 0.15
                my_metric{quantile="0.5"} 0.15
                my_metric{quantile="0.9"} 0.15
                my_metric_count 1
                my_metric_sum 0.15

                PLAINTEXT,
                <<<'PLAINTEXT'
                #TYPE my_metric summary
                my_metric{quantile="0.1"} 0.15
                my_metric{quantile="0.5"} 0.15
                my_metric{quantile="0.9"} 0.56
                my_metric_count 2
                my_metric_sum 0.71

                PLAINTEXT
            ]
        ];

        yield 'KO - Labels values not matching labels' => [
            'name'           => 'my_metric',
            'labels'         => ['method'],
            'help'           => '',
            'quantiles'      => null,
            'values'         => [1],
            'labelValues'    => [[]],
            'error'          => "Invalid labels: count labels given '0' are not matching the count labels defined '1'",
            'plaintexts'     => [[]]
        ];

        yield "KO - Label 'quantile' is reserved" => [
            'name'           => 'my_metric',
            'labels'         => ['quantile'],
            'help'           => '',
            'quantiles'      => null,
            'values'         => [1],
            'labelValues'    => [[]],
            'error'          => "Invalid label name: summary label 'quantile' is reserved.",
            'plaintexts'     => [[]]
        ];
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    #[DataProvider('provideObserveDataCases')]
    public function testObserve(string $name, array $labels, string $help, ?array $quantiles, array $values, array $labelValues, ?string $error, array $plaintexts): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor($name, $labels);

        if ($help !== '') {
            $descriptor->setHelp($help);
        }

        if ($quantiles !== null) {
            $descriptor->setSummaryQuantiles($quantiles);
        }

        $storage = $this->storage;

        $summary = new Summary($storage, $descriptor);

        $count = \count($plaintexts);
        for ($idx = 0; $idx < $count; ++$idx) {
            $summary->observe($values[$idx], $labelValues[$idx]);

            static::assertSame($plaintexts[$idx], $summary->expose());
        }
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    public function testUsortSameValues(): void
    {
        $descriptor = new Descriptor('my_metric');
        $descriptor->setSummaryQuantiles([0.1, 0.9]);

        $summary = new Summary($this->storage, $descriptor);

        $summary->observe(1);
        $summary->observe(1);

        $expected = <<<'PLAINTEXT'
            #TYPE my_metric summary
            my_metric{quantile="0.1"} 1
            my_metric{quantile="0.9"} 1
            my_metric_count 2
            my_metric_sum 2

            PLAINTEXT;

        static::assertSame($expected, $summary->expose());
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    public function testRemoveExpiredSamples(): void
    {
        $descriptor = new Descriptor('my_metric');
        $descriptor->setSummaryTTL(1);
        $descriptor->setSummaryQuantiles([0.1, 0.9]);

        $summary = new Summary($this->storage, $descriptor);

        $summary->observe(1);
        $summary->observe(2);
        $summary->observe(3);

        $this->storage->setTimeFunction(static function (): int { return \time() + 2; });

        static::assertSame('', $summary->expose());
    }

    /** @throws CollectorException */
    public function testExposeAfterDeclaration(): void
    {
        $descriptor = new Descriptor('my_other_metric');

        $summary = new Summary($this->storage, $descriptor);

        static::assertSame(<<<'PLAINTEXT'

            PLAINTEXT, $summary->expose());
    }
}
