<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Recorder;

use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsLoggerInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum;
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder;
use Maatify\EventLogging\Tests\Support\FixedClock;
use Maatify\EventLogging\Tests\Support\SpyLogger;
use Maatify\EventLogging\Tests\Support\ThrowingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecuritySignalsRecorderTest extends TestCase
{
    public function testRecordCommandSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(SecuritySignalsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $logger->expects($this->once())
            ->method('write')
            ->with($this->callback(function (SecuritySignalRecordDTO $dto) use ($clock) {
                return $dto->signalType === 'brute_force_attempt'
                    && $dto->severity === 'CRITICAL'
                    && $dto->actorType === 'USER'
                    && $dto->actorId === 42
                    && $dto->occurredAt === $clock->now();
            }));

        $recorder = new SecuritySignalsRecorder($logger, $clock, $spyLogger);

        $command = new \Maatify\EventLogging\SecuritySignals\Command\RecordSecuritySignalCommand(
            signalType: 'brute_force_attempt',
            severity: SecuritySignalSeverityEnum::CRITICAL,
            actorType: SecuritySignalActorTypeEnum::USER,
            actorId: 42
        );

        $recorder->recordCommand($command);

        $this->assertEmpty($spyLogger->logs);
    }

    public function testRecordSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(SecuritySignalsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $logger->expects($this->once())
            ->method('write')
            ->with($this->callback(function (SecuritySignalRecordDTO $dto) use ($clock) {
                return $dto->signalType === 'brute_force_attempt'
                    && $dto->severity === 'CRITICAL'
                    && $dto->actorType === 'USER'
                    && $dto->actorId === 42
                    && $dto->occurredAt === $clock->now();
            }));

        $recorder = new SecuritySignalsRecorder($logger, $clock, $spyLogger);

        $recorder->record(
            signalType: 'brute_force_attempt',
            severity: SecuritySignalSeverityEnum::CRITICAL,
            actorType: SecuritySignalActorTypeEnum::USER,
            actorId: 42
        );

        $this->assertEmpty($spyLogger->logs);
    }

    public function testFailOpenOnStorageFailure(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(SecuritySignalsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $logger->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new SecuritySignalsRecorder($logger, $clock, $spyLogger);

        $recorder->record(
            signalType: 'brute_force_attempt',
            severity: SecuritySignalSeverityEnum::CRITICAL,
            actorType: SecuritySignalActorTypeEnum::USER,
            actorId: 42
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('error', $spyLogger->logs[0]['level']);
        $this->assertEquals('SecuritySignals logging failed', $spyLogger->logs[0]['message']);
    }

    public function testFallbackLoggerThrowingDoesNotLeakException(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(SecuritySignalsLoggerInterface::class);
        $throwingLogger = new ThrowingLogger();

        $logger->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new SecuritySignalsRecorder($logger, $clock, $throwingLogger);

        $recorder->record(
            signalType: 'brute_force_attempt',
            severity: SecuritySignalSeverityEnum::CRITICAL,
            actorType: SecuritySignalActorTypeEnum::USER,
            actorId: 42
        );
        $this->expectNotToPerformAssertions();
    }

    public function testMetadataTooLargeLogsWarning(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(SecuritySignalsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $largeMetadata = ['data' => str_repeat('a', 66000)];

        $logger->expects($this->once())
            ->method('write')
            ->with($this->callback(function (SecuritySignalRecordDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to size limit'];
            }));

        $recorder = new SecuritySignalsRecorder($logger, $clock, $spyLogger);

        $recorder->record(
            signalType: 'brute_force_attempt',
            severity: SecuritySignalSeverityEnum::CRITICAL,
            actorType: SecuritySignalActorTypeEnum::USER,
            actorId: 42,
            metadata: $largeMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('SecuritySignals metadata exceeded limit', $spyLogger->logs[0]['message']);
    }

    public function testMetadataEncodingFailureLogsWarning(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(SecuritySignalsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $badMetadata = ['data' => "\xB1\x31"]; // Invalid UTF-8

        $logger->expects($this->once())
            ->method('write')
            ->with($this->callback(function (SecuritySignalRecordDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to encoding error'];
            }));

        $recorder = new SecuritySignalsRecorder($logger, $clock, $spyLogger);

        $recorder->record(
            signalType: 'brute_force_attempt',
            severity: SecuritySignalSeverityEnum::CRITICAL,
            actorType: SecuritySignalActorTypeEnum::USER,
            actorId: 42,
            metadata: $badMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('SecuritySignals metadata JSON encoding failed', $spyLogger->logs[0]['message']);
    }
}
