<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Recorder;

use InvalidArgumentException;
use Maatify\EventLogging\AuthoritativeAudit\Command\RecordAuthoritativeAuditCommand;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditOutboxWriterInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;
use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditRiskLevelEnum;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
use Maatify\EventLogging\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditRecorderTest extends TestCase
{
    public function testRecordCommandSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(AuthoritativeAuditOutboxWriterInterface::class);

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (AuthoritativeAuditOutboxWriteDTO $dto) use ($clock) {
                return $dto->action === 'test-action'
                    && $dto->targetType === 'user'
                    && $dto->targetId === 123
                    && $dto->riskLevel === 'HIGH'
                    && $dto->actorType === 'SYSTEM' // Policy normalizes string actorType to uppercase?
                    && $dto->actorId === 456
                    && $dto->payload === ['key' => 'value']
                    && $dto->correlationId === 'corr-123'
                    && $dto->createdAt === $clock->now();
            }));

        $recorder = new AuthoritativeAuditRecorder($writer, $clock);

        $command = new RecordAuthoritativeAuditCommand(
            action: 'test-action',
            targetType: 'user',
            targetId: 123,
            riskLevel: AuthoritativeAuditRiskLevelEnum::HIGH,
            actorType: 'system',
            actorId: 456,
            payload: ['key' => 'value'],
            correlationId: 'corr-123'
        );

        $recorder->recordCommand($command);
    }

    public function testRecordSuccessfulPath(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(AuthoritativeAuditOutboxWriterInterface::class);

        $writer->expects($this->once())
            ->method('write')
            ->with($this->callback(function (AuthoritativeAuditOutboxWriteDTO $dto) {
                return $dto->action === 'test-action'
                    && $dto->targetType === 'user'
                    && $dto->targetId === 123
                    && $dto->riskLevel === 'HIGH'
                    && $dto->actorType === 'SYSTEM'
                    && $dto->actorId === 456
                    && $dto->payload === ['key' => 'value']
                    && $dto->correlationId === 'corr-123';
            }));

        $recorder = new AuthoritativeAuditRecorder($writer, $clock);

        $recorder->record(
            action: 'test-action',
            targetType: 'user',
            targetId: 123,
            riskLevel: AuthoritativeAuditRiskLevelEnum::HIGH,
            actorType: 'system',
            actorId: 456,
            payload: ['key' => 'value'],
            correlationId: 'corr-123'
        );
    }

    public function testInvalidPayloadThrowsException(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(AuthoritativeAuditOutboxWriterInterface::class);

        $recorder = new AuthoritativeAuditRecorder($writer, $clock);

        $this->expectException(InvalidArgumentException::class);

        $recorder->record(
            action: 'test-action',
            targetType: 'user',
            targetId: 123,
            riskLevel: AuthoritativeAuditRiskLevelEnum::HIGH,
            actorType: 'system',
            actorId: 456,
            payload: ['password' => 'secret'], // Contains a secret, policy should reject
            correlationId: 'corr-123'
        );
    }

    public function testStorageFailureThrowsStorageException(): void
    {
        $clock = new FixedClock();
        $writer = $this->createMock(AuthoritativeAuditOutboxWriterInterface::class);

        $writer->method('write')->willThrowException(new AuthoritativeAuditStorageException('Storage failed'));

        $recorder = new AuthoritativeAuditRecorder($writer, $clock);

        $this->expectException(AuthoritativeAuditStorageException::class);

        $recorder->record(
            action: 'test-action',
            targetType: 'user',
            targetId: 123,
            riskLevel: AuthoritativeAuditRiskLevelEnum::HIGH,
            actorType: 'system',
            actorId: 456,
            payload: ['key' => 'value'],
            correlationId: 'corr-123'
        );
    }
}
