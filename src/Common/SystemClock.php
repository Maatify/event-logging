<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Common;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\SharedCommon\Contracts\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function __construct(
        private readonly DateTimeZone $timezone = new DateTimeZone('UTC')
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }
}
