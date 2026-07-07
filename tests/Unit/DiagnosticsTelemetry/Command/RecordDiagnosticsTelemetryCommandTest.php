<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Command;

use InvalidArgumentException;
use Maatify\EventLogging\DiagnosticsTelemetry\Command\RecordDiagnosticsTelemetryCommand;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeEnum;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityEnum;
use PHPUnit\Framework\TestCase;

class RecordDiagnosticsTelemetryCommandTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $command = new RecordDiagnosticsTelemetryCommand(
            'API_LATENCY',
            'WARNING',
            'SYSTEM',
            1,
            'corr',
            'req',
            'api.call',
            '127.0.0.1',
            'Mozilla',
            150,
            ['foo' => 'bar']
        );

        $this->assertSame('API_LATENCY', $command->eventKey);
        $this->assertSame('WARNING', $command->severity);
        $this->assertSame('SYSTEM', $command->actorType);
        $this->assertSame(1, $command->actorId);
        $this->assertSame('corr', $command->correlationId);
        $this->assertSame('req', $command->requestId);
        $this->assertSame('api.call', $command->routeName);
        $this->assertSame('127.0.0.1', $command->ipAddress);
        $this->assertSame('Mozilla', $command->userAgent);
        $this->assertSame(150, $command->durationMs);
        $this->assertSame(['foo' => 'bar'], $command->metadata);
    }

    public function testValidConstructionWithEnums(): void
    {
        $command = new RecordDiagnosticsTelemetryCommand(
            'CRASH',
            DiagnosticsTelemetrySeverityEnum::CRITICAL,
            DiagnosticsTelemetryActorTypeEnum::SYSTEM
        );

        $this->assertSame(DiagnosticsTelemetrySeverityEnum::CRITICAL, $command->severity);
        $this->assertSame(DiagnosticsTelemetryActorTypeEnum::SYSTEM, $command->actorType);
    }

    public function testNullOptionals(): void
    {
        $command = new RecordDiagnosticsTelemetryCommand(
            'STARTUP',
            'INFO',
            'SYSTEM'
        );

        $this->assertNull($command->actorId);
        $this->assertNull($command->durationMs);
        $this->assertNull($command->metadata);
    }

    public function testThrowsOnEmptyEventKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Diagnostics telemetry event key must not be empty.');

        new RecordDiagnosticsTelemetryCommand(
            '   ',
            'INFO',
            'SYSTEM'
        );
    }

    public function testThrowsOnInvalidActorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Diagnostics telemetry actor id must be a positive integer when provided.');

        new RecordDiagnosticsTelemetryCommand(
            'STARTUP',
            'INFO',
            'SYSTEM',
            0
        );
    }
}
