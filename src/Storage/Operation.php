<?php

declare(strict_types=1);

namespace Rancoud\Prometheus\Storage;

/** Used by Gauge. */
enum Operation
{
    case Set;
    case Add;
    case Sub;
}
