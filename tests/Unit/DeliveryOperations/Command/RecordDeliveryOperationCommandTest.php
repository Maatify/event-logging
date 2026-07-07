<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use Maatify\EventLogging\DeliveryOperations\Command\RecordDeliveryOperationCommand;
use PHPUnit\Framework\TestCase;

class RecordDeliveryOperationCommandTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $sched = new DateTimeImmutable();
        $comp = new DateTimeImmutable();

        $command = new RecordDeliveryOperationCommand(
            'EMAIL',
            'SEND',
            'SUCCESS',
            1,
            'SYSTEM',
            123,
            'USER',
            456,
            $sched,
            $comp,
            'corr',
            'req',
            'smtp',
            'msg-1',
            'err-0',
            'None',
            ['foo' => 'bar']
        );

        $this->assertSame('EMAIL', $command->channel);
        $this->assertSame('SEND', $command->operationType);
        $this->assertSame('SUCCESS', $command->status);
        $this->assertSame(1, $command->attemptNo);
        $this->assertSame('SYSTEM', $command->actorType);
        $this->assertSame(123, $command->actorId);
        $this->assertSame('USER', $command->targetType);
        $this->assertSame(456, $command->targetId);
        $this->assertSame($sched, $command->scheduledAt);
        $this->assertSame($comp, $command->completedAt);
        $this->assertSame('corr', $command->correlationId);
        $this->assertSame('req', $command->requestId);
        $this->assertSame('smtp', $command->provider);
        $this->assertSame('msg-1', $command->providerMessageId);
        $this->assertSame('err-0', $command->errorCode);
        $this->assertSame('None', $command->errorMessage);
        $this->assertSame(['foo' => 'bar'], $command->metadata);
    }

    public function testNullOptionals(): void
    {
        $command = new RecordDeliveryOperationCommand(
            'SMS',
            'SEND',
            'PENDING'
        );

        $this->assertSame(0, $command->attemptNo);
        $this->assertNull($command->actorType);
        $this->assertNull($command->actorId);
        $this->assertNull($command->targetType);
        $this->assertNull($command->targetId);
        $this->assertNull($command->scheduledAt);
        $this->assertNull($command->completedAt);
    }

    public function testThrowsOnEmptyChannel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery operation channel must not be empty.');

        new RecordDeliveryOperationCommand(
            '   ',
            'SEND',
            'SUCCESS'
        );
    }

    public function testThrowsOnEmptyOperationType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery operation type must not be empty.');

        new RecordDeliveryOperationCommand(
            'EMAIL',
            '   ',
            'SUCCESS'
        );
    }

    public function testThrowsOnEmptyStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery operation status must not be empty.');

        new RecordDeliveryOperationCommand(
            'EMAIL',
            'SEND',
            ''
        );
    }

    public function testThrowsOnInvalidAttemptNo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery operation attempt number must be zero or greater.');

        new RecordDeliveryOperationCommand(
            'EMAIL',
            'SEND',
            'FAILED',
            -1
        );
    }

    public function testThrowsOnInvalidActorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery operation actorId must be a positive integer when provided.');

        new RecordDeliveryOperationCommand(
            'EMAIL',
            'SEND',
            'SUCCESS',
            0,
            'SYSTEM',
            0
        );
    }

    public function testThrowsOnInvalidTargetId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery operation targetId must be a positive integer when provided.');

        new RecordDeliveryOperationCommand(
            'EMAIL',
            'SEND',
            'SUCCESS',
            0,
            'SYSTEM',
            1,
            'USER',
            -1
        );
    }
}
