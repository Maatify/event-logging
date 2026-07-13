<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit;

use Maatify\EventLogging\Common\SystemClock;
use PHPUnit\Framework\TestCase;

final class InfrastructureSmokeTest extends TestCase
{
    public function testAutoloadingAndBasicInstantiation(): void
    {
        $clock = new SystemClock();

        $this->assertInstanceOf(\DateTimeImmutable::class, $clock->now());
    }
}
