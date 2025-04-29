<?php

declare(strict_types=1);

namespace tests\SQLite;

use Rancoud\Database\Configurator;
use Rancoud\Database\Database;
use Rancoud\Database\DatabaseException;
use Rancoud\Prometheus\Exception\StorageException;
use Rancoud\Prometheus\Storage\SQLite;
use tests\AbstractGauge;

/** @internal */
class GaugeTest extends AbstractGauge
{
    /**
     * @throws DatabaseException
     * @throws StorageException
     */
    protected function setUp(): void
    {
        $params = [
            'driver'    => 'sqlite',
            'host'      => ':memory',
            'user'      => '',
            'password'  => '',
            'database'  => ''
        ];

        $this->storage = new SQLite(new Database(new Configurator($params)));
    }
}
