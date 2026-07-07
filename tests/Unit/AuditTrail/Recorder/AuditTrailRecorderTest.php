<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Recorder;

use Maatify\EventLogging\AuditTrail\Command\RecordAuditTrailCommand;
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailLoggerInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailRecordDTO;
use Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
use Maatify\EventLogging\Tests\Support\FixedClock;
use Maatify\EventLogging\Tests\Support\SpyLogger;
use Maatify\EventLogging\Tests\Support\ThrowingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditTrailRecorderTest extends TestCase
{
    public function testRecordSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(AuditTrailLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $logger->expects($this->once())
            ->method('write')
            ->with($this->callback(function (AuditTrailRecordDTO $dto) use ($clock) {
                return $dto->eventKey === 'test-event'
                    && $dto->actorType === 'USER'
                    && $dto->actorId === 123
                    && $dto->entityType === 'document'
                    && $dto->entityId === 456
                    && $dto->referrerPath === '/test'
                    && $dto->occurredAt === $clock->now();
            }));

        $recorder = new AuditTrailRecorder($logger, $clock, $spyLogger);

        $recorder->record(
            eventKey: 'test-event',
            actorType: AuditTrailActorTypeEnum::USER,
            actorId: 123,
            entityType: 'document',
            entityId: 456,
            referrerPath: '/test?query=string' // should be sanitized to /test
        );

        $this->assertEmpty($spyLogger->logs);
    }

    public function testFailOpenOnStorageFailure(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(AuditTrailLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $logger->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new AuditTrailRecorder($logger, $clock, $spyLogger);

        // This should not throw
        $recorder->record(
            eventKey: 'test-event',
            actorType: AuditTrailActorTypeEnum::USER,
            actorId: 123,
            entityType: 'document',
            entityId: 456
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('error', $spyLogger->logs[0]['level']);
        $this->assertEquals('AuditTrail logging failed', $spyLogger->logs[0]['message']);
    }

    public function testFallbackLoggerThrowingDoesNotLeakException(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(AuditTrailLoggerInterface::class);
        $throwingLogger = new ThrowingLogger();

        $logger->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new AuditTrailRecorder($logger, $clock, $throwingLogger);

        // This should not throw despite both storage and fallback logger failing
        $recorder->record(
            eventKey: 'test-event',
            actorType: AuditTrailActorTypeEnum::USER,
            actorId: 123,
            entityType: 'document',
            entityId: 456
        );
        $this->expectNotToPerformAssertions(); // Reached end successfully
    }

    public function testMetadataTooLargeLogsWarning(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(AuditTrailLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $largeMetadata = ['data' => str_repeat('a', 66000)]; // Exceeds typical 65k limit

        $logger->expects($this->once())
            ->method('write')
            ->with($this->callback(function (AuditTrailRecordDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to size limit'];
            }));

        $recorder = new AuditTrailRecorder($logger, $clock, $spyLogger);

        $recorder->record(
            eventKey: 'test-event',
            actorType: AuditTrailActorTypeEnum::USER,
            actorId: 123,
            entityType: 'document',
            entityId: 456,
            metadata: $largeMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('AuditTrail metadata exceeded limit', $spyLogger->logs[0]['message']);
    }

    public function testMetadataEncodingFailureLogsWarning(): void
    {
        $clock = new FixedClock();
        $logger = $this->createMock(AuditTrailLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $badMetadata = ['data' => "\xB1\x31"]; // Invalid UTF-8

        $logger->expects($this->once())
            ->method('write')
            ->with($this->callback(function (AuditTrailRecordDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to encoding error'];
            }));

        $recorder = new AuditTrailRecorder($logger, $clock, $spyLogger);

        $recorder->record(
            eventKey: 'test-event',
            actorType: AuditTrailActorTypeEnum::USER,
            actorId: 123,
            entityType: 'document',
            entityId: 456,
            metadata: $badMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('AuditTrail metadata JSON encoding failed', $spyLogger->logs[0]['message']);
    }
}
