<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Recorder;

use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryLoggerInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryRecorder;
use Maatify\EventLogging\Tests\Support\FixedClock;
use Maatify\EventLogging\Tests\Support\SpyLogger;
use Maatify\EventLogging\Tests\Support\ThrowingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DiagnosticsTelemetryRecorderTest extends TestCase
{
    public function testRecordCommandSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DiagnosticsTelemetryLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (DiagnosticsTelemetryEventDTO $dto) use ($clock) {
                return $dto->eventKey === 'app.start'
                    && $dto->severity->value() === 'INFO'
                    && $dto->context->actorType->value() === 'SYSTEM'
                    && $dto->durationMs === 120
                    && $dto->context->occurredAt === $clock->now();
            }));

        $recorder = new DiagnosticsTelemetryRecorder($writer, $clock, $spyLogger);

        $command = new \Maatify\EventLogging\DiagnosticsTelemetry\Command\RecordDiagnosticsTelemetryCommand(
            eventKey: 'app.start',
            severity: 'info',
            actorType: 'system',
            durationMs: 120
        );

        $recorder->recordCommand($command);

        $this->assertEmpty($spyLogger->logs);
    }

    public function testRecordSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DiagnosticsTelemetryLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (DiagnosticsTelemetryEventDTO $dto) use ($clock) {
                return $dto->eventKey === 'app.start'
                    && $dto->severity->value() === 'INFO'
                    && $dto->context->actorType->value() === 'SYSTEM'
                    && $dto->durationMs === 120
                    && $dto->context->occurredAt === $clock->now();
            }));

        $recorder = new DiagnosticsTelemetryRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            eventKey: 'app.start',
            severity: 'info',
            actorType: 'system',
            durationMs: 120
        );

        $this->assertEmpty($spyLogger->logs);
    }

    public function testFailOpenOnStorageFailure(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DiagnosticsTelemetryLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $writer->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new DiagnosticsTelemetryRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            eventKey: 'app.start',
            severity: 'info',
            actorType: 'system'
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('error', $spyLogger->logs[0]['level']);
        $this->assertEquals('Telemetry logging failed', $spyLogger->logs[0]['message']);
    }

    public function testFallbackLoggerThrowingDoesNotLeakException(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DiagnosticsTelemetryLoggerInterface::class);
        $throwingLogger = new ThrowingLogger();

        $writer->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new DiagnosticsTelemetryRecorder($writer, $clock, $throwingLogger);

        $recorder->record(
            eventKey: 'app.start',
            severity: 'info',
            actorType: 'system'
        );
        $this->expectNotToPerformAssertions();
    }

    public function testMetadataTooLargeLogsWarning(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DiagnosticsTelemetryLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $largeMetadata = ['data' => str_repeat('a', 66000)];

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (DiagnosticsTelemetryEventDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to size limit'];
            }));

        $recorder = new DiagnosticsTelemetryRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            eventKey: 'app.start',
            severity: 'info',
            actorType: 'system',
            metadata: $largeMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('Telemetry metadata exceeded limit', $spyLogger->logs[0]['message']);
    }

    public function testMetadataEncodingFailureLogsWarning(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DiagnosticsTelemetryLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $badMetadata = ['data' => "\xB1\x31"]; // Invalid UTF-8

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (DiagnosticsTelemetryEventDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to encoding error'];
            }));

        $recorder = new DiagnosticsTelemetryRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            eventKey: 'app.start',
            severity: 'info',
            actorType: 'system',
            metadata: $badMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('Telemetry metadata JSON encoding failed', $spyLogger->logs[0]['message']);
    }
}
