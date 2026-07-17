<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql;

use Exception;
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

    public function testMapEmptyJsonObject(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['event_id' => 'event-1', 'changes' => '{}'];
        $dto = $mapper->map($row);
        $this->assertSame([], $dto->changes);
    }

    public function testMapEmptyJsonList(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['event_id' => 'event-1', 'changes' => '[]'];
        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testMapNumericKeyArray(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['event_id' => 'event-1', 'changes' => '["x"]'];
        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testMapScalarJson(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['event_id' => 'event-1', 'changes' => '"scalar"'];
        $dto = $mapper->map($row);
        $this->assertNull($dto->changes);
    }

    public function testMissingAndNonStringScalarFieldsMapToNull(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        // Provide non-string or missing fields
        $row = [
            'id' => 1, // int instead of string
            'event_id' => null, // missing string
            'actor_id' => '42', // string numeric mapped to int
            'actor_type' => 123, // int instead of string
            'action' => null,
            'target_id' => 100, // int instead of string
            'target_type' => 456,
            'changes' => 789, // non-string json
        ];

        $dto = $mapper->map($row);

        $this->assertSame(1, $dto->id);
        $this->assertSame('', $dto->eventId); // Default cast
        $this->assertSame(42, $dto->actorId);
        $this->assertNull($dto->actorType);
        $this->assertSame('', $dto->action);
        $this->assertSame(100, $dto->targetId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->changes);
        $this->assertNull($dto->ipAddress);
        $this->assertNull($dto->userAgent);
        $this->assertNull($dto->correlationId);
    }

    public function testMissingOccurredAtFallback(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['event_id' => 'event-1'];
        $dto = $mapper->map($row);
        $this->assertSame('1970-01-01 00:00:00.000000', $dto->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $dto->occurredAt->getTimezone()->getName());
    }

    public function testInvalidOccurredAtThrowsException(): void
    {
        $mapper = new AuthoritativeAuditRowMapper();
        $row = ['event_id' => 'event-1', 'occurred_at' => 'invalid-date'];
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid occurred_at format");
        $mapper->map($row);
    }
}
