<?php

/** @noinspection PhpTooManyParametersInspection */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Exception\CollectorException;
use Rancoud\Prometheus\Exception\DescriptorException;
use Rancoud\Prometheus\Gauge;
use Rancoud\Prometheus\Storage\Adapter;

/** @internal */
abstract class AbstractGauge extends TestCase
{
    protected Adapter $storage;

    public static function provideIncDataCases(): iterable
    {
        yield 'OK - 2 labels + help' => [
            'name'          => 'my_metric',
            'labels'        => ['method', 'path'],
            'help'          => 'description here',
            'values'        => [1, 3, 7, 10],
            'labelValues'   => [['GET', 'login'], ['GET', 'login'], ['POST', 'contact'], ['PUT', '‍💻金\"\n\\']],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} 1

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} 4

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} 4
                my_metric{method="POST",path="contact"} 7

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} 4
                my_metric{method="POST",path="contact"} 7
                my_metric{method="PUT",path="‍💻金\\\"\\n\\"} 10

                PLAINTEXT
            ]
        ];

        yield 'OK - 0 label' => [
            'name'          => 'my_metric',
            'labels'        => [],
            'help'          => '',
            'values'        => [1, 3],
            'labelValues'   => [[], []],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #TYPE my_metric gauge
                my_metric 1

                PLAINTEXT,
                <<<'PLAINTEXT'
                #TYPE my_metric gauge
                my_metric 4

                PLAINTEXT
            ]
        ];

        yield 'KO - Labels values not matching labels' => [
            'name'          => 'my_metric',
            'labels'        => ['method'],
            'help'          => '',
            'values'        => [1],
            'labelValues'   => [[]],
            'error'         => "Invalid labels: count labels given '0' are not matching the count labels defined '1'",
            'plaintexts'    => [[]]
        ];
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    #[DataProvider('provideIncDataCases')]
    public function testInc(string $name, array $labels, string $help, array $values, array $labelValues, ?string $error, array $plaintexts): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor($name, $labels);

        if ($help !== '') {
            $descriptor->setHelp($help);
        }

        $gauge = new Gauge($this->storage, $descriptor);

        $count = \count($plaintexts);
        for ($idx = 0; $idx < $count; ++$idx) {
            $gauge->inc($values[$idx], $labelValues[$idx]);

            static::assertSame($plaintexts[$idx], $gauge->expose());
        }
    }

    public static function provideDecDataCases(): iterable
    {
        yield 'OK' => [
            'name'          => 'my_metric',
            'labels'        => ['method', 'path'],
            'help'          => 'description here',
            'values'        => [1, 3],
            'labelValues'   => [['GET', 'login'], ['GET', 'login']],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} -1

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} -4

                PLAINTEXT
            ]
        ];

        yield 'KO - Labels values not matching labels' => [
            'name'          => 'my_metric',
            'labels'        => ['method'],
            'help'          => '',
            'values'        => [1],
            'labelValues'   => [[]],
            'error'         => "Invalid labels: count labels given '0' are not matching the count labels defined '1'",
            'plaintexts'    => [[]]
        ];
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    #[DataProvider('provideDecDataCases')]
    public function testDec(string $name, array $labels, string $help, array $values, array $labelValues, ?string $error, array $plaintexts): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor($name, $labels);

        if ($help !== '') {
            $descriptor->setHelp($help);
        }

        $gauge = new Gauge($this->storage, $descriptor);

        $count = \count($plaintexts);
        for ($idx = 0; $idx < $count; ++$idx) {
            $gauge->dec($values[$idx], $labelValues[$idx]);

            static::assertSame($plaintexts[$idx], $gauge->expose());
        }
    }

    public static function provideSetDataCases(): iterable
    {
        yield 'OK' => [
            'name'          => 'my_metric',
            'labels'        => ['method', 'path'],
            'help'          => 'description here',
            'values'        => [1, 3],
            'labelValues'   => [['GET', 'login'], ['GET', 'login']],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} 1

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} 3

                PLAINTEXT
            ]
        ];

        yield 'KO - Labels values not matching labels' => [
            'name'          => 'my_metric',
            'labels'        => ['method'],
            'help'          => '',
            'values'        => [1],
            'labelValues'   => [[]],
            'error'         => "Invalid labels: count labels given '0' are not matching the count labels defined '1'",
            'plaintexts'    => [[]]
        ];
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    #[DataProvider('provideSetDataCases')]
    public function testSet(string $name, array $labels, string $help, array $values, array $labelValues, ?string $error, array $plaintexts): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor($name, $labels);

        if ($help !== '') {
            $descriptor->setHelp($help);
        }

        $gauge = new Gauge($this->storage, $descriptor);

        $count = \count($plaintexts);
        for ($idx = 0; $idx < $count; ++$idx) {
            $gauge->set($values[$idx], $labelValues[$idx]);

            static::assertSame($plaintexts[$idx], $gauge->expose());
        }
    }

    public static function provideSetToCurrentTimeDataCases(): iterable
    {
        yield 'OK' => [
            'name'          => 'my_metric',
            'labels'        => ['method', 'path'],
            'help'          => 'description here',
            'labelValues'   => [['GET', 'login'], ['GET', 'login']],
            'error'         => null,
            'plaintexts'    => [<<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} xxx

                PLAINTEXT,
                <<<'PLAINTEXT'
                #HELP my_metric description here
                #TYPE my_metric gauge
                my_metric{method="GET",path="login"} xxx

                PLAINTEXT
            ]
        ];

        yield 'KO - Labels values not matching labels' => [
            'name'          => 'my_metric',
            'labels'        => ['method'],
            'help'          => '',
            'labelValues'   => [[]],
            'error'         => "Invalid labels: count labels given '0' are not matching the count labels defined '1'",
            'plaintexts'    => [[]]
        ];
    }

    /**
     * @throws CollectorException
     * @throws DescriptorException
     */
    #[DataProvider('provideSetToCurrentTimeDataCases')]
    public function testSetToCurrentTime(string $name, array $labels, string $help, array $labelValues, ?string $error, array $plaintexts): void
    {
        if ($error !== null) {
            $this->expectException(CollectorException::class);
            $this->expectExceptionMessage($error);
        }

        $descriptor = new Descriptor($name, $labels);

        if ($help !== '') {
            $descriptor->setHelp($help);
        }

        $gauge = new Gauge($this->storage, $descriptor);

        $count = \count($plaintexts);
        for ($idx = 0; $idx < $count; ++$idx) {
            $gauge->setToCurrentTime($labelValues[$idx]);

            $output = \str_replace('xxx', \time() . '', $plaintexts[$idx]);

            static::assertSame($output, $gauge->expose());
        }
    }

    public function testExposeAfterDeclaration(): void
    {
        $descriptor = new Descriptor('my_other_metric');

        $gauge = new Gauge($this->storage, $descriptor);

        static::assertSame(<<<'PLAINTEXT'

            PLAINTEXT, $gauge->expose());
    }
}
