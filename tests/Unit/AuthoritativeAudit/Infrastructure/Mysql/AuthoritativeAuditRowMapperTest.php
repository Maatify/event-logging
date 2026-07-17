<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql;

use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditRowMapperTest extends TestCase
{
    public function testMapValidRow(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'actor_type' => 'user',
            'actor_id' => '42',
            'action' => 'create',
            'target_type' => 'resource',
            'target_id' => '100',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'correlation_id' => 'corr-1',
            'changes' => '{"key":"value"}',
            'occurred_at' => '2024-01-01 00:00:00.000000',
        ];

        $dto = $mapper->map($row);

        $this->assertSame(1, $dto->id);
        $this->assertSame('event-1', $dto->eventId);
        $this->assertSame('user', $dto->actorType);
        $this->assertSame(42, $dto->actorId);
        $this->assertSame('create', $dto->action);
        $this->assertSame('resource', $dto->targetType);
        $this->assertSame(100, $dto->targetId);
        $this->assertSame('127.0.0.1', $dto->ipAddress);
        $this->assertSame('test', $dto->userAgent);
        $this->assertSame('corr-1', $dto->correlationId);
        $this->assertSame(['key' => 'value'], $dto->changes);
        $this->assertSame('2024-01-01 00:00:00', $dto->occurredAt->format('Y-m-d H:i:s'));
    }

    public function testMapCorruptJson(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'changes' => 'invalid-json',
        ];

        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }


    public function testMissingAndNonStringFields(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => 'abc',
            'event_id' => 123,
            'actor_type' => 123,
            'actor_id' => 'abc',
            'action' => 123,
            'target_type' => 123,
            'target_id' => 'abc',
            'ip_address' => 123,
            'user_agent' => 123,
            'correlation_id' => 123,
        ];

        $dto = $mapper->map($row);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->eventId);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertSame('', $dto->action);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertNull($dto->ipAddress);
        $this->assertNull($dto->userAgent);
        $this->assertNull($dto->correlationId);
    }

    public function testMissingChangesIsMappedToNull(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['id' => '1'];
        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testEmptyJsonObjectIsMappedToEmptyArray(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['id' => '1', 'changes' => '{}'];
        $dto = $mapper->map($row);
        $this->assertSame([], $dto->changes);
    }

    public function testScalarJsonIsMappedToNull(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['id' => '1', 'changes' => '"scalar"'];
        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testListJsonIsMappedToNull(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['id' => '1', 'changes' => '["item1", "item2"]'];
        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testMissingOccurredAtFallsBackToEpoch(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['id' => '1'];
        $dto = $mapper->map($row);
        $this->assertSame('1970-01-01 00:00:00', $dto->occurredAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $dto->occurredAt->getTimezone()->getName());
    }

    public function testInvalidOccurredAtThrowsException(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['id' => '1', 'occurred_at' => 'invalid-date'];
        $this->expectException(\Exception::class);
        $mapper->map($row);
    }

    public function testOccurredAtMicrosecondsArePreserved(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['id' => '1', 'occurred_at' => '2024-01-01 00:00:00.123456'];
        $dto = $mapper->map($row);
        $this->assertSame('123456', $dto->occurredAt->format('u'));
    }
}
