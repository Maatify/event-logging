<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeEnum;
use PHPUnit\Framework\TestCase;

class DiagnosticsTelemetryContextDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new DiagnosticsTelemetryContextDTO(
            DiagnosticsTelemetryActorTypeEnum::SYSTEM,
            123,
            'corr',
            'req',
            'route',
            '127.0.0.1',
            'Mozilla',
            $date
        );

        $expected = [
            'actorType' => 'SYSTEM',
            'actorId' => 123,
            'correlationId' => 'corr',
            'requestId' => 'req',
            'routeName' => 'route',
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Mozilla',
            'occurredAt' => $date->format(DATE_ATOM),
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new DiagnosticsTelemetryContextDTO(
            DiagnosticsTelemetryActorTypeEnum::ANONYMOUS,
            null,
            null,
            null,
            null,
            null,
            null,
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
