<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Recorder;

use DateTimeImmutable;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsLoggerInterface;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryChannelEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryOperationTypeEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryStatusEnum;
use Maatify\EventLogging\DeliveryOperations\Recorder\DeliveryOperationsRecorder;
use Maatify\EventLogging\Tests\Support\FixedClock;
use Maatify\EventLogging\Tests\Support\SpyLogger;
use Maatify\EventLogging\Tests\Support\ThrowingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeliveryOperationsRecorderTest extends TestCase
{
    public function testRecordCommandSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $spyLogger = new SpyLogger();
        $scheduledAt = new DateTimeImmutable('2023-01-01T10:00:00Z');

        $writer->expects($this->once())
            ->method('log')
            ->with($this->callback(function (DeliveryOperationRecordDTO $dto) use ($clock, $scheduledAt) {
                return $dto->channel === 'EMAIL'
                    && $dto->operationType === 'NOTIFICATION'
                    && $dto->status === 'SENT'
                    && $dto->attemptNo === 1
                    && $dto->actorType === 'SYSTEM'
                    && $dto->actorId === 123
                    && $dto->scheduledAt === $scheduledAt
                    && $dto->occurredAt === $clock->now();
            }));

        $recorder = new DeliveryOperationsRecorder($writer, $clock, $spyLogger);

        $command = new \Maatify\EventLogging\DeliveryOperations\Command\RecordDeliveryOperationCommand(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            attemptNo: 1,
            actorType: 'system',
            actorId: 123,
            scheduledAt: $scheduledAt
        );

        $recorder->recordCommand($command);

        $this->assertEmpty($spyLogger->logs);
    }

    public function testRecordSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $spyLogger = new SpyLogger();
        $scheduledAt = new DateTimeImmutable('2023-01-01T10:00:00Z');

        $writer->expects($this->once())
            ->method('log')
            ->with($this->callback(function (DeliveryOperationRecordDTO $dto) use ($clock, $scheduledAt) {
                return $dto->channel === 'EMAIL'
                    && $dto->operationType === 'NOTIFICATION'
                    && $dto->status === 'SENT'
                    && $dto->attemptNo === 1
                    && $dto->actorType === 'SYSTEM'
                    && $dto->actorId === 123
                    && $dto->scheduledAt === $scheduledAt
                    && $dto->occurredAt === $clock->now();
            }));

        $recorder = new DeliveryOperationsRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            attemptNo: 1,
            actorType: 'system',
            actorId: 123,
            scheduledAt: $scheduledAt
        );

        $this->assertEmpty($spyLogger->logs);
    }

    public function testFailOpenOnStorageFailure(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $writer->method('log')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new DeliveryOperationsRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('error', $spyLogger->logs[0]['level']);
        $this->assertEquals('DeliveryOperations logging failed', $spyLogger->logs[0]['message']);
    }

    public function testFallbackLoggerThrowingDoesNotLeakException(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $throwingLogger = new ThrowingLogger();

        $writer->method('log')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new DeliveryOperationsRecorder($writer, $clock, $throwingLogger);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT
        );
        $this->expectNotToPerformAssertions();
    }

    public function testMetadataTooLargeLogsWarning(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $largeMetadata = ['data' => str_repeat('a', 66000)];

        $writer->expects($this->once())
            ->method('log')
            ->with($this->callback(function (DeliveryOperationRecordDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped: too large'];
            }));

        $recorder = new DeliveryOperationsRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            metadata: $largeMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('DeliveryOperations metadata too large', $spyLogger->logs[0]['message']);
    }

    public function testMetadataEncodingFailureLogsWarning(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $spyLogger = new SpyLogger();

        $badMetadata = ['data' => "\xB1\x31"]; // Invalid UTF-8

        $writer->expects($this->once())
            ->method('log')
            ->with($this->callback(function (DeliveryOperationRecordDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped: encoding error'];
            }));

        $recorder = new DeliveryOperationsRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            metadata: $badMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('DeliveryOperations metadata encoding failed', $spyLogger->logs[0]['message']);
    }
}
