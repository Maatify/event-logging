<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Recorder;

use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceWriterInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceRecorder;
use Maatify\EventLogging\Tests\Support\FixedClock;
use Maatify\EventLogging\Tests\Support\SpyLogger;
use Maatify\EventLogging\Tests\Support\ThrowingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BehaviorTraceRecorderTest extends TestCase
{
    public function testRecordCommandSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(BehaviorTraceWriterInterface::class);
        $spyLogger = new SpyLogger();

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (BehaviorTraceEventDTO $dto) use ($clock) {
                return $dto->action === 'cmd-click'
                    && $dto->entityType === 'button'
                    && $dto->entityId === 12
                    && $dto->context->actorType->value() === 'USER'
                    && $dto->context->actorId === 34
                    && $dto->context->occurredAt === $clock->now();
            }));

        $recorder = new BehaviorTraceRecorder($writer, $clock, $spyLogger);

        $command = new \Maatify\EventLogging\BehaviorTrace\Command\RecordBehaviorTraceCommand(
            action: 'cmd-click',
            actorType: 'user',
            actorId: 34,
            entityType: 'button',
            entityId: 12
        );

        $recorder->recordCommand($command);

        $this->assertEmpty($spyLogger->logs);
    }

    public function testRecordSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(BehaviorTraceWriterInterface::class);
        $spyLogger = new SpyLogger();

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (BehaviorTraceEventDTO $dto) use ($clock) {
                return $dto->action === 'click'
                    && $dto->entityType === 'button'
                    && $dto->entityId === 12
                    && $dto->context->actorType->value() === 'USER'
                    && $dto->context->actorId === 34
                    && $dto->context->occurredAt === $clock->now();
            }));

        $recorder = new BehaviorTraceRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            action: 'click',
            actorType: 'user',
            actorId: 34,
            entityType: 'button',
            entityId: 12
        );

        $this->assertEmpty($spyLogger->logs);
    }

    public function testFailOpenOnStorageFailure(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(BehaviorTraceWriterInterface::class);
        $spyLogger = new SpyLogger();

        $writer->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new BehaviorTraceRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            action: 'click',
            actorType: 'user'
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('error', $spyLogger->logs[0]['level']);
        $this->assertEquals('Behavior trace logging failed', $spyLogger->logs[0]['message']);
    }

    public function testFallbackLoggerThrowingDoesNotLeakException(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(BehaviorTraceWriterInterface::class);
        $throwingLogger = new ThrowingLogger();

        $writer->method('write')->willThrowException(new RuntimeException('Storage failed'));

        $recorder = new BehaviorTraceRecorder($writer, $clock, $throwingLogger);

        $recorder->record(
            action: 'click',
            actorType: 'user'
        );
        $this->expectNotToPerformAssertions();
    }

    public function testMetadataTooLargeLogsWarning(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(BehaviorTraceWriterInterface::class);
        $spyLogger = new SpyLogger();

        $largeMetadata = ['data' => str_repeat('a', 66000)];

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (BehaviorTraceEventDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to size limit'];
            }));

        $recorder = new BehaviorTraceRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            action: 'click',
            actorType: 'user',
            metadata: $largeMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('Behavior trace metadata exceeded limit', $spyLogger->logs[0]['message']);
    }

    public function testMetadataEncodingFailureLogsWarning(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(BehaviorTraceWriterInterface::class);
        $spyLogger = new SpyLogger();

        $badMetadata = ['data' => "\xB1\x31"]; // Invalid UTF-8

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (BehaviorTraceEventDTO $dto) {
                return $dto->metadata === ['error' => 'Metadata dropped due to encoding error'];
            }));

        $recorder = new BehaviorTraceRecorder($writer, $clock, $spyLogger);

        $recorder->record(
            action: 'click',
            actorType: 'user',
            metadata: $badMetadata
        );

        $this->assertCount(1, $spyLogger->logs);
        $this->assertEquals('warning', $spyLogger->logs[0]['level']);
        $this->assertStringContainsString('Behavior trace metadata JSON encoding failed', $spyLogger->logs[0]['message']);
    }
}
