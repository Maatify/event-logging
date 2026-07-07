<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Command;

use InvalidArgumentException;
use Maatify\EventLogging\SecuritySignals\Command\RecordSecuritySignalCommand;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum;
use PHPUnit\Framework\TestCase;

class RecordSecuritySignalCommandTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $command = new RecordSecuritySignalCommand(
            'LOGIN_FAILED',
            'HIGH',
            'USER',
            123,
            ['foo' => 'bar'],
            'corr',
            'req',
            'login',
            '127.0.0.1',
            'Mozilla'
        );

        $this->assertSame('LOGIN_FAILED', $command->signalType);
        $this->assertSame('HIGH', $command->severity);
        $this->assertSame('USER', $command->actorType);
        $this->assertSame(123, $command->actorId);
        $this->assertSame(['foo' => 'bar'], $command->metadata);
        $this->assertSame('corr', $command->correlationId);
        $this->assertSame('req', $command->requestId);
        $this->assertSame('login', $command->routeName);
        $this->assertSame('127.0.0.1', $command->ipAddress);
        $this->assertSame('Mozilla', $command->userAgent);
    }

    public function testValidConstructionWithEnums(): void
    {
        $command = new RecordSecuritySignalCommand(
            'LOGOUT',
            SecuritySignalSeverityEnum::WARNING,
            SecuritySignalActorTypeEnum::ADMIN,
            123
        );

        $this->assertSame(SecuritySignalSeverityEnum::WARNING, $command->severity);
        $this->assertSame(SecuritySignalActorTypeEnum::ADMIN, $command->actorType);
    }

    public function testNullOptionals(): void
    {
        $command = new RecordSecuritySignalCommand(
            'LOGIN',
            'LOW',
            'SYSTEM',
            null
        );

        $this->assertNull($command->actorId);
        $this->assertNull($command->metadata);
        $this->assertNull($command->correlationId);
        $this->assertNull($command->requestId);
    }

    public function testThrowsOnEmptySignalType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Security signal type must not be empty.');

        new RecordSecuritySignalCommand(
            '  ',
            'LOW',
            'ADMIN',
            1
        );
    }

    public function testThrowsOnInvalidActorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Security signal actor id must be a positive integer when provided.');

        new RecordSecuritySignalCommand(
            'LOGIN',
            'LOW',
            'ADMIN',
            0
        );
    }
}
