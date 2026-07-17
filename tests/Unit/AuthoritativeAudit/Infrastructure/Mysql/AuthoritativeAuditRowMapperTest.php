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

    public function testMapMissingNonStringFieldsEpochUtc(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'actor_type' => null,
            'actor_id' => null,
            'action' => 'create',
            'target_type' => null,
            'target_id' => null,
            // 'ip_address' missing
            // 'user_agent' missing
            // 'correlation_id' missing
            // 'changes' missing
            // 'occurred_at' missing
        ];

        $dto = $mapper->map($row);

        $this->assertSame(1, $dto->id);
        $this->assertSame('event-1', $dto->eventId);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertSame('create', $dto->action);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertNull($dto->ipAddress);
        $this->assertNull($dto->userAgent);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->changes);
        $this->assertSame('1970-01-01 00:00:00', $dto->occurredAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $dto->occurredAt->getTimezone()->getName());
    }

    public function testMapInvalidDateThrowsException(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'action' => 'create',
            'occurred_at' => 'not-a-date'
        ];

        $this->expectException(\Exception::class);
        $mapper->map($row);
    }

    public function testMapMicrosecondsPreserved(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'action' => 'create',
            'occurred_at' => '2024-01-01 12:34:56.789012'
        ];

        $dto = $mapper->map($row);
        $this->assertSame('2024-01-01 12:34:56.789012', $dto->occurredAt->format('Y-m-d H:i:s.u'));
    }

    public function testMapTimezoneUtc(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'action' => 'create',
            'occurred_at' => '2024-01-01 12:34:56'
        ];

        $dto = $mapper->map($row);
        $this->assertSame('UTC', $dto->occurredAt->getTimezone()->getName());
    }

    public function testMapScalarJsonMapsToNull(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'action' => 'create',
            'changes' => '"scalar-string"'
        ];

        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testMapListJsonMapsToNull(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'action' => 'create',
            'changes' => '["item1", "item2"]'
        ];

        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testMapEmptyObjectMapsToEmptyArray(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = [
            'id' => '1',
            'event_id' => 'event-1',
            'action' => 'create',
            'changes' => '{}'
        ];

        $dto = $mapper->map($row);
        $this->assertSame([], $dto->changes);
    }
}