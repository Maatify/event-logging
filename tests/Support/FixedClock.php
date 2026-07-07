<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use DateTimeImmutable;
use Maatify\EventLogging\Common\ClockInterface;

class FixedClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('2023-01-01T12:00:00Z');
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
