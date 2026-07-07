<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Command;

use InvalidArgumentException;
use Maatify\EventLogging\BehaviorTrace\Command\RecordBehaviorTraceCommand;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeEnum;
use PHPUnit\Framework\TestCase;

class RecordBehaviorTraceCommandTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $command = new RecordBehaviorTraceCommand(
            'VIEW',
            'USER',
            123,
            'PAGE',
            456,
            'corr',
            'req',
            'page.view',
            '127.0.0.1',
            'Mozilla',
            ['foo' => 'bar']
        );

        $this->assertSame('VIEW', $command->action);
        $this->assertSame('USER', $command->actorType);
        $this->assertSame(123, $command->actorId);
        $this->assertSame('PAGE', $command->entityType);
        $this->assertSame(456, $command->entityId);
        $this->assertSame('corr', $command->correlationId);
        $this->assertSame('req', $command->requestId);
        $this->assertSame('page.view', $command->routeName);
        $this->assertSame('127.0.0.1', $command->ipAddress);
        $this->assertSame('Mozilla', $command->userAgent);
        $this->assertSame(['foo' => 'bar'], $command->metadata);
    }

    public function testValidConstructionWithEnum(): void
    {
        $command = new RecordBehaviorTraceCommand(
            'CLICK',
            BehaviorTraceActorTypeEnum::USER
        );

        $this->assertSame(BehaviorTraceActorTypeEnum::USER, $command->actorType);
    }

    public function testNullOptionals(): void
    {
        $command = new RecordBehaviorTraceCommand(
            'SCROLL',
            'ANONYMOUS'
        );

        $this->assertNull($command->actorId);
        $this->assertNull($command->entityType);
        $this->assertNull($command->entityId);
        $this->assertNull($command->metadata);
    }

    public function testThrowsOnEmptyAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Behavior trace action must not be empty.');

        new RecordBehaviorTraceCommand(
            '   ',
            'USER'
        );
    }

    public function testThrowsOnInvalidActorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Behavior trace actorId must be a positive integer when provided.');

        new RecordBehaviorTraceCommand(
            'VIEW',
            'USER',
            0
        );
    }

    public function testThrowsOnInvalidEntityId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Behavior trace entityId must be a positive integer when provided.');

        new RecordBehaviorTraceCommand(
            'VIEW',
            'USER',
            1,
            'PAGE',
            -1
        );
    }
}
