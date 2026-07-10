<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeEnum;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityEnum;
use PHPUnit\Framework\TestCase;

class DiagnosticsTelemetryEventDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $context = new DiagnosticsTelemetryContextDTO(
            DiagnosticsTelemetryActorTypeEnum::SYSTEM,
            123,
            'corr',
            'req',
            'route',
            '127.0.0.1',
            'Mozilla',
            $date
        );

        $dto = new DiagnosticsTelemetryEventDTO(
            123,
            'evt-1',
            'API_CALL',
            DiagnosticsTelemetrySeverityEnum::INFO,
            $context,
            150,
            ['foo' => 'bar']
        );

        $expected = [
            'id' => 123,
            'eventId' => 'evt-1',
            'eventKey' => 'API_CALL',
            'severity' => 'INFO',
            'context' => $context->jsonSerialize(),
            'durationMs' => 150,
            'metadata' => ['foo' => 'bar'],
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $context = new DiagnosticsTelemetryContextDTO(
            DiagnosticsTelemetryActorTypeEnum::ANONYMOUS,
            null,
            null,
            null,
            null,
            null,
            null,
            $date
        );

        $dto = new DiagnosticsTelemetryEventDTO(
            123,
            'evt-1',
            'API_CALL',
            DiagnosticsTelemetrySeverityEnum::WARNING,
            $context,
            null,
            null
        );

        $this->assertNull($dto->durationMs);
        $this->assertNull($dto->metadata);
    }
}
