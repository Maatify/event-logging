<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Command;

use InvalidArgumentException;
use Maatify\EventLogging\AuthoritativeAudit\Command\RecordAuthoritativeAuditCommand;
use PHPUnit\Framework\TestCase;

class RecordAuthoritativeAuditCommandTest extends TestCase
{
    public function testValidConstructionWithScalarEnums(): void
    {
        $command = new RecordAuthoritativeAuditCommand(
            'CREATE',
            'USER',
            123,
            'HIGH',
            'ADMIN',
            456,
            ['foo' => 'bar'],
            'corr-123'
        );

        $this->assertSame('CREATE', $command->action);
        $this->assertSame('USER', $command->targetType);
        $this->assertSame(123, $command->targetId);
        $this->assertSame('HIGH', $command->riskLevel);
        $this->assertSame('ADMIN', $command->actorType);
        $this->assertSame(456, $command->actorId);
        $this->assertSame(['foo' => 'bar'], $command->payload);
        $this->assertSame('corr-123', $command->correlationId);
    }

    public function testValidConstructionWithNullOptionals(): void
    {
        $command = new RecordAuthoritativeAuditCommand(
            'LOGIN',
            'SYSTEM',
            null,
            'LOW',
            'ANONYMOUS',
            null,
            [],
            'corr-456'
        );

        $this->assertNull($command->targetId);
        $this->assertNull($command->actorId);
    }

    public function testThrowsOnEmptyAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authoritative audit action must not be empty.');

        new RecordAuthoritativeAuditCommand(
            '   ',
            'USER',
            1,
            'LOW',
            'ADMIN',
            1,
            [],
            'corr-123'
        );
    }

    public function testThrowsOnEmptyTargetType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authoritative audit target type must not be empty.');

        new RecordAuthoritativeAuditCommand(
            'CREATE',
            '',
            1,
            'LOW',
            'ADMIN',
            1,
            [],
            'corr-123'
        );
    }

    public function testThrowsOnEmptyCorrelationId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authoritative audit correlation id must not be empty.');

        new RecordAuthoritativeAuditCommand(
            'CREATE',
            'USER',
            1,
            'LOW',
            'ADMIN',
            1,
            [],
            '  '
        );
    }

    public function testThrowsOnInvalidTargetId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authoritative audit target id must be a positive integer when provided.');

        new RecordAuthoritativeAuditCommand(
            'CREATE',
            'USER',
            0,
            'LOW',
            'ADMIN',
            1,
            [],
            'corr'
        );
    }

    public function testThrowsOnInvalidActorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authoritative audit actor id must be a positive integer when provided.');

        new RecordAuthoritativeAuditCommand(
            'CREATE',
            'USER',
            1,
            'LOW',
            'ADMIN',
            -1,
            [],
            'corr'
        );
    }
}
