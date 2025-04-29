<?php

declare(strict_types=1);

namespace Rancoud\Prometheus;

class Registry
{
    /** @var Collector[] List of metrics. */
    protected array $collectors = [];

    /** Default Registry. */
    protected static ?self $defaultRegistry = null;

    /** Registers metric. */
    public function register(Collector ...$collectors): void
    {
        foreach ($collectors as $collector) {
            $this->collectors[$collector->metricName()] = $collector;
        }
    }

    /** Unregisters metric. */
    public function unregister(Collector ...$collectors): void
    {
        foreach ($collectors as $collector) {
            unset($this->collectors[$collector->metricName()]);
        }
    }

    /** Returns raw metrics registered (descriptor + samples) as iterable. */
    public function collect(): iterable
    {
        foreach ($this->collectors as $collector) {
            yield from $collector->collect();
        }
    }

    /** Returns text of metrics registered as string. */
    public function expose(): string
    {
        $output = '';

        foreach ($this->collectors as $collector) {
            $output .= $collector->expose();
        }

        return $output;
    }

    /** Registers metric in the default Registry (singleton). */
    public static function registerInDefault(Collector $collector): void
    {
        if (self::$defaultRegistry === null) {
            self::$defaultRegistry = new self();
        }

        self::$defaultRegistry->register($collector);
    }

    /** Returns the default Registry (singleton). */
    public static function getDefault(): self
    {
        return self::$defaultRegistry;
    }
}
