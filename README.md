# Prometheus Package

![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/rancoud/Prometheus)
[![Packagist Version](https://img.shields.io/packagist/v/rancoud/Prometheus)](https://packagist.org/packages/rancoud/Prometheus)
[![Packagist Downloads](https://img.shields.io/packagist/dt/rancoud/Prometheus)](https://packagist.org/packages/rancoud/Prometheus)
[![Composer dependencies](https://img.shields.io/badge/dependencies-0-brightgreen)](https://github.com/rancoud/Prometheus/blob/master/composer.json)
[![Test workflow](https://img.shields.io/github/actions/workflow/status/rancoud/Prometheus/test.yml?branch=master)](https://github.com/rancoud/Prometheus/actions/workflows/test.yml)
[![Codecov](https://img.shields.io/codecov/c/github/rancoud/Prometheus?logo=codecov)](https://codecov.io/gh/rancoud/Prometheus)

Prometheus client library using database, memory storage.

Based on documentation [https://prometheus.io/docs/instrumenting/writing_clientlibs/](https://prometheus.io/docs/instrumenting/writing_clientlibs/)
and [https://github.com/PromPHP/prometheus_client_php](https://github.com/PromPHP/prometheus_client_php)

Use `rancoud/Database` package ([https://github.com/rancoud/Database](https://github.com/rancoud/Database)) when using MySQL, PostgreSQL or SQLite.

## Installation
```php
composer require rancoud/prometheus
```

## How to use it?
### Counter metric example
Simple counter and expose result
```php
use Rancoud\Prometheus\Counter;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Storage\InMemory;

// Define a counter
$counter = new Counter(
        new InMemory(),         // <- InMemory is the storage engine used.
        new Descriptor("login") // <- Descriptor describe your metric.
    );

// By default it increase by 1
$counter->inc();

// Also you can use a value other than 1 (always positive in case of counter)
$counter->inc(3);

// Now you can expose as plain text result
echo $counter->expose();

// Result of expose() below:
#TYPE login counter
login 4

```

Counter with help text and labels
```php
use Rancoud\Prometheus\Counter;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Storage\InMemory;

$descriptor = new Descriptor("request_count", ['method', 'path'])
    ->setHelp('Number of request by method and path');

$counter = new Counter(new InMemory(), $descriptor);

$counter->inc(5, ['GET', 'home']);
$counter->inc(3, ['GET', 'login']);
$counter->inc(1, ['POST', 'login']);

echo $counter->expose();

// Result of expose() below:
#HELP request_count Number of request by method and path
#TYPE request_count counter
request_count{method="GET",path="home"} 5
request_count{method="GET",path="login"} 3
request_count{method="POST",path="login"} 1

```

### Gauge metric example
```php
use Rancoud\Prometheus\Gauge;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Storage\InMemory;

// Define a gauge
$gauge = new Gauge(
        new InMemory(),
        new Descriptor("account_count")
            ->setHelp('Number of account')
    );

// You can set a value
$gauge->set(100);

// You can increment
$gauge->inc(15);

// You can decrement
$gauge->dec(5);

echo $gauge->expose();

// Result of expose() below:
#HELP account_count Number of account
#TYPE account_count gauge
account_count 110

```

### Histogram metric example
```php
use Rancoud\Prometheus\Histogram;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Storage\InMemory;

// Define a histogram
$histogram = new Histogram(
        new InMemory(),
        new Descriptor("http_request_duration_seconds")
    );

// You can observe a value
$histogram->observe(0.56);

echo $histogram->expose();

// Result of expose() below:
#TYPE http_request_duration_seconds histogram
http_request_duration_seconds_bucket{le="0.005"} 0
http_request_duration_seconds_bucket{le="0.01"} 0
http_request_duration_seconds_bucket{le="0.025"} 0
http_request_duration_seconds_bucket{le="0.05"} 0
http_request_duration_seconds_bucket{le="0.075"} 0
http_request_duration_seconds_bucket{le="0.1"} 0
http_request_duration_seconds_bucket{le="0.25"} 0
http_request_duration_seconds_bucket{le="0.5"} 0
http_request_duration_seconds_bucket{le="0.75"} 1
http_request_duration_seconds_bucket{le="1"} 1
http_request_duration_seconds_bucket{le="2.5"} 1
http_request_duration_seconds_bucket{le="5"} 1
http_request_duration_seconds_bucket{le="7.5"} 1
http_request_duration_seconds_bucket{le="10"} 1
http_request_duration_seconds_bucket{le="+Inf"} 1
http_request_duration_seconds_count 1
http_request_duration_seconds_sum 0.56

```
You can set your own buckets.
```php
new Descriptor("http_request_duration_seconds")->setHistogramBuckets([0, 5, 10]);
```

### Summary metric example
```php
use Rancoud\Prometheus\Summary;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Storage\InMemory;

// Define a summary
$summary = new Summary(
        new InMemory(),
        new Descriptor("http_request_duration_seconds")
    );

// You can observe a value
$summary->observe(0.56);

echo $summary->expose();

// Result of expose() below:
#TYPE http_request_duration_seconds summary
http_request_duration_seconds{quantile="0.01"} 0.56
http_request_duration_seconds{quantile="0.05"} 0.56
http_request_duration_seconds{quantile="0.5"} 0.56
http_request_duration_seconds{quantile="0.95"} 0.56
http_request_duration_seconds{quantile="0.99"} 0.56
http_request_duration_seconds_count 1
http_request_duration_seconds_sum 0.56

```
You can set your own quantiles.
```php
new Descriptor("http_request_duration_seconds")->setSummaryQuantiles([0.1, 0.5, 0.9]);
```
You can change the TTL in seconds you want to keep the observed values.
```php
new Descriptor("http_request_duration_seconds")->setSummaryTTL(10);
```

### Registry
A registry is an object where all metrics are stored.  

Registry instance example
```php
use Rancoud\Prometheus\Counter;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Registry;
use Rancoud\Prometheus\Storage\InMemory;

// Define a registry
$registry = new Registry();

// Define 2 counters
$counter1 = new Counter(new InMemory(), new Descriptor("login"));
$counter2 = new Counter(new InMemory(), new Descriptor("logout"));

// Add counters in registry
$registry->register($counter1, $counter2);

// Update counters otherwise is not exposed
$counter1->inc(4);
$counter2->inc(2);

// Now you can expose as plain text result
echo $registry->expose();

// Result of expose() below:
#TYPE login counter
login 4
#TYPE logout counter
logout 2

```

Default Registry with static Singleton example
```php
use Rancoud\Prometheus\Counter;
use Rancoud\Prometheus\Descriptor;
use Rancoud\Prometheus\Registry;
use Rancoud\Prometheus\Storage\InMemory;

// Define 2 counters AND register them in the default registry
$counter1 = new Counter(new InMemory(), new Descriptor("login"))->register();
$counter2 = new Counter(new InMemory(), new Descriptor("logout"))->register();

// Update counters otherwise is not exposed
$counter1->inc(4);
$counter2->inc(2);

// Now you can expose as plain text result
echo Registry::getDefault()->expose();

// Result of expose() below:
#TYPE login counter
login 4
#TYPE logout counter
logout 2

```

### Database storage example
Using SQLite with memory database is same as using InMemory Database.
```php
use Rancoud\Database\Configurator;
use Rancoud\Database\Database;
use Rancoud\Prometheus\Storage\SQLite;

$params = [
    'driver'    => 'sqlite',
    'host'      => '',
    'user'      => '',
    'password'  => '',
    'database'  => 'prometheus.db'
];

$configurator = new Configurator($params);

$database = new Database($configurator);

$storage = new Rancoud\Prometheus\SQLite($database);

$counter = new Counter($storage, new Descriptor("example"));
```

## Metric
Constructor
```php
public function __construct(Adapter $storage, Descriptor $descriptor)
```

Returns raw metrics (descriptor + samples) as iterable.
```php
public function collect(): iterable
```

Returns text of metric as string.
```php
public function expose(): string
```

Returns metric name.
```php
public function metricName(): string
```

Register in the default Registry.
```php
public function register(): self
```

### Counter Metric
Increments counter.
```php
public function inc(float|int $value = 1, array $labels = []): void
```

### Gauge Metric
Increments counter.
```php
public function inc(float|int $value = 1, array $labels = []): void
```

Decrements counter.
```php
public function dec(float|int $value = 1, array $labels = []): void
```

Sets value of gauge.
```php
public function set(float|int $value, array $labels = []): void
```

Sets value of gauge with function \time() to use current Unix timestamp.
```php
public function setToCurrentTime(array $labels = []): void
```

### Histogram Metric
Adds a new sample.
```php
public function observe(float $value, array $labels = []): void
```

Generates linear buckets.  
Creates 'count' regular buckets, each 'width' wide, where the lowest bucket has an upper bound of 'start'.
```php
public static function linearBuckets(float $start, float $width, int $countBuckets): array
```

Generates exponential buckets.  
Creates 'count' regular buckets, where the lowest bucket has an upper bound of 'start'
and each following bucket's upper bound is 'factor' times the previous bucket's upper bound.
```php
public static function exponentialBuckets(float $start, float $growthFactor, int $countBuckets): array
```

### Summary Metric
Adds a new sample.
```php
public function observe(float $value, array $labels = []): void
```

## Descriptor
Constructor
```php
public function __construct(string $name, array $labels = [])
```

When exposed it will output a line #HELP {your message}.
```php
public function setHelp(string $help): self
```

Set histogram buckets instead of using default buckets.
```php
public function setHistogramBuckets(array $buckets): self
```

Set summary TTL instead of using default TTL.
```php
public function setSummaryTTL(int $ttlInSeconds): self
```

Set summary quantiles instead of using default quantiles.
```php
public function setSummaryQuantiles(array $quantiles): self
```

Returns name.
```php
public function name(): string
```

Returns labels.
```php
public function labels(): array
```

Returns labels count.
```php
public function labelsCount(): int
```

Returns histogram buckets.
```php
public function buckets(): array
```

Returns summary quantiles.
```php
public function quantiles(): array
```

Returns summary TTL.
```php
public function ttlInSeconds(): int
```

Exports HELP.
```php
public function exportHelp(): string
```

Exports TYPE.
```php
public function exportType(string $type): string
```

Exports value (counter, gauge, histogram _sum and _count, summary _sum and _count).
```php
public function exportValue(float|int $value, array $labelValues, string $suffixName = ''): string
```

Exports value (histogram).
```php
public function exportHistogramValue(string $bucket, int $value, array $labelValues): string
```

Exports value (summary).
```php
public function exportSummaryValue(float $quantile, array $values, array $labelValues): string
```

## Registry
Registers metric.
```php
public function register(Collector ...$collectors): void
```

Unregisters metric.
```php
public function unregister(Collector ...$collectors): void
```

Returns raw metrics registered (descriptor + samples) as iterable.
```php
public function collect(): iterable
```

Returns text of metrics registered as string.
```php
public function expose(): string
```

Registers metric in the default Registry (singleton).
```php
public static function registerInDefault(Collector $collector): void
```

Returns the default Registry (singleton).
```php
public static function getDefault(): self
```

## Storage
Returns metrics (counter, gauge, histogram and summary) as iterable.  
If metric type and name is provided it will return only the specify metric.
```php
public function collect(string $metricType = '', string $metricName = ''): iterable
```

Returns text of metrics (counter, gauge, histogram and summary) as iterable.  
If metric type and name is provided it will return only the specify metric.
```php
public function expose(string $metricType = '', string $metricName = ''): iterable
```

Updates counter metric.
```php
public function updateCounter(Descriptor $descriptor, float|int $value = 1, array $labelValues = []): void
```

Updates gauge metric.
```php
public function updateGauge(Descriptor $descriptor, Operation $operation, float|int $value = 1, array $labelValues = []): void
```

Adds sample to histogram metric.
```php
public function updateHistogram(Descriptor $descriptor, float $value, array $labelValues = []): void
```

Adds sample to summary metric.
```php
public function updateSummary(Descriptor $descriptor, float $value, array $labelValues = []): void
```

Removes all data saved.
```php
public function wipeStorage(): void
```

Overrides Time Function for summary metric.
```php
public function setTimeFunction(callable|string $time): void
```

### InMemory
Returns text of counters metric as iterable.
```php
public function exposeCounters(string $metricName = ''): iterable
```

Returns text of gauges metric as iterable.
```php
public function exposeGauges(string $metricName = ''): iterable
```

Returns text of histograms metric as iterable.
```php
public function exposeHistograms(string $metricName = ''): iterable
```

Returns text of summaries metric as iterable.
```php
public function exposeSummaries(string $metricName = ''): iterable
```

## How to Dev
`composer ci` for php-cs-fixer and phpunit and coverage  
`composer lint` for php-cs-fixer  
`composer test` for phpunit and coverage
