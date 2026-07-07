<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use PHPUnit\Framework\TestCase;

class SecuritySignalRecordDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new SecuritySignalRecordDTO(
            'evt-1',
            'ADMIN',
            123,
            'LOGIN_FAILED',
            'HIGH',
            'corr',
            'req',
            'login',
            '127.0.0.1',
            'Mozilla',
            ['foo' => 'bar'],
            $date
        );

        $expected = [
            'eventId' => 'evt-1',
            'actorType' => 'ADMIN',
            'actorId' => 123,
            'signalType' => 'LOGIN_FAILED',
            'severity' => 'HIGH',
            'correlationId' => 'corr',
            'requestId' => 'req',
            'routeName' => 'login',
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Mozilla',
            'metadata' => ['foo' => 'bar'],
            'occurredAt' => $date->format(DATE_ATOM),
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new SecuritySignalRecordDTO(
            'evt-1',
            'SYSTEM',
            null,
            'LOGIN_FAILED',
            'HIGH',
            null,
            null,
            null,
            null,
            null,
            [],
            $date
        );

        $this->assertNull($dto->actorId);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->routeName);
        $this->assertNull($dto->ipAddress);
        $this->assertNull($dto->userAgent);
    }
}
