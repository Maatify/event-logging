<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Common;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
