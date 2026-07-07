<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Command;

use InvalidArgumentException;
use Maatify\EventLogging\AuditTrail\Command\RecordAuditTrailCommand;
use Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum;
use PHPUnit\Framework\TestCase;

class RecordAuditTrailCommandTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $command = new RecordAuditTrailCommand(
            'USER_CREATED',
            'ADMIN',
            123,
            'USER',
            456,
            'PROFILE',
            789,
            ['foo' => 'bar'],
            'profile_edit',
            '/profile/edit',
            'example.com',
            'corr-1',
            'req-1',
            'user_create',
            '127.0.0.1',
            'Mozilla'
        );

        $this->assertSame('USER_CREATED', $command->eventKey);
        $this->assertSame('ADMIN', $command->actorType);
        $this->assertSame(123, $command->actorId);
        $this->assertSame('USER', $command->entityType);
        $this->assertSame(456, $command->entityId);
        $this->assertSame('PROFILE', $command->subjectType);
        $this->assertSame(789, $command->subjectId);
        $this->assertSame(['foo' => 'bar'], $command->metadata);
        $this->assertSame('profile_edit', $command->referrerRouteName);
        $this->assertSame('/profile/edit', $command->referrerPath);
        $this->assertSame('example.com', $command->referrerHost);
        $this->assertSame('corr-1', $command->correlationId);
        $this->assertSame('req-1', $command->requestId);
        $this->assertSame('user_create', $command->routeName);
        $this->assertSame('127.0.0.1', $command->ipAddress);
        $this->assertSame('Mozilla', $command->userAgent);
    }

    public function testValidConstructionWithEnum(): void
    {
        $command = new RecordAuditTrailCommand(
            'USER_CREATED',
            AuditTrailActorTypeEnum::ADMIN,
            123,
            'USER',
            456
        );

        $this->assertSame(AuditTrailActorTypeEnum::ADMIN, $command->actorType);
    }

    public function testNullOptionals(): void
    {
        $command = new RecordAuditTrailCommand(
            'LOGIN',
            'SYSTEM',
            null,
            'APP',
            null
        );

        $this->assertNull($command->actorId);
        $this->assertNull($command->entityId);
        $this->assertNull($command->subjectType);
        $this->assertNull($command->subjectId);
    }

    public function testThrowsOnEmptyEventKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit trail event key must not be empty.');

        new RecordAuditTrailCommand(
            '  ',
            'ADMIN',
            1,
            'USER',
            1
        );
    }

    public function testThrowsOnEmptyEntityType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit trail entity type must not be empty.');

        new RecordAuditTrailCommand(
            'LOGIN',
            'ADMIN',
            1,
            '  ',
            1
        );
    }

    public function testThrowsOnInvalidActorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit trail actorId must be a positive integer when provided.');

        new RecordAuditTrailCommand(
            'LOGIN',
            'ADMIN',
            0,
            'USER',
            1
        );
    }

    public function testThrowsOnInvalidEntityId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit trail entityId must be a positive integer when provided.');

        new RecordAuditTrailCommand(
            'LOGIN',
            'ADMIN',
            1,
            'USER',
            -1
        );
    }
}
