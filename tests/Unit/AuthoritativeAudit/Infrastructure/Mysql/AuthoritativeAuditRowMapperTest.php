<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql;

use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper
 */
final class AuthoritativeAuditRowMapperTest extends TestCase
{
    private AuthoritativeAuditRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AuthoritativeAuditRowMapper();
    }

    public function testItIsInternalAndFinal(): void
    {
        $reflector = new ReflectionClass(AuthoritativeAuditRowMapper::class);

        $this->assertTrue($reflector->isFinal());
        $this->assertStringContainsString('@internal', (string) $reflector->getDocComment());
    }

    public function testItMapsFullValidRow(): void
    {
        $row = [
            'id' => '123',
            'event_id' => 'uuid-1',
            'actor_type' => 'sys',
            'actor_id' => '42',
            'action' => 'update',
            'target_type' => 'file',
            'target_id' => '100',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'correlation_id' => 'corr-1',
            'changes' => '{"foo": "bar"}',
            'occurred_at' => '2023-01-01 10:00:00.123456',
        ];

        $dto = $this->mapper->map($row);

        $this->assertSame(123, $dto->id);
        $this->assertSame('uuid-1', $dto->eventId);
        $this->assertSame('sys', $dto->actorType);
        $this->assertSame(42, $dto->actorId);
        $this->assertSame('update', $dto->action);
        $this->assertSame('file', $dto->targetType);
        $this->assertSame(100, $dto->targetId);
        $this->assertSame('127.0.0.1', $dto->ipAddress);
        $this->assertSame('test-agent', $dto->userAgent);
        $this->assertSame('corr-1', $dto->correlationId);
        $this->assertSame(['foo' => 'bar'], $dto->changes);

        $this->assertSame('2023-01-01 10:00:00.123456', $dto->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $dto->occurredAt->getTimezone()->getName());
    }

    public function testItHandlesNumericConversionsFromBothIntegersAndStrings(): void
    {
        $row = [
            'id' => 123,
            'actor_id' => '42',
            'target_id' => 100,
        ];

        $dto = $this->mapper->map($row);

        $this->assertSame(123, $dto->id);
        $this->assertSame(42, $dto->actorId);
        $this->assertSame(100, $dto->targetId);

        $row2 = [
            'id' => '123',
            'actor_id' => 42,
            'target_id' => '100',
        ];
        $dto2 = $this->mapper->map($row2);
        $this->assertSame(123, $dto2->id);
        $this->assertSame(42, $dto2->actorId);
        $this->assertSame(100, $dto2->targetId);
    }

    public function testItHandlesEmptyRowWithMissingFieldsAndReturnsFallbacks(): void
    {
        $dto = $this->mapper->map([]);

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
        $this->assertNull($dto->changes);
        $this->assertSame('1970-01-01 00:00:00.000000', $dto->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $dto->occurredAt->getTimezone()->getName());
    }

    public function testItHandlesMissingAndWrongTypeFallbacks(): void
    {
        $row = [
            'id' => 'not-numeric',
            'event_id' => 123, // not string
            'actor_type' => 123,
            'actor_id' => 'not-numeric',
            'action' => null,
            'target_type' => null,
            'target_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'correlation_id' => null,
            'changes' => null,
            'occurred_at' => null,
        ];

        $dto = $this->mapper->map($row);

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
        $this->assertNull($dto->changes);

        $this->assertSame('1970-01-01 00:00:00.000000', $dto->occurredAt->format('Y-m-d H:i:s.u'));
    }

    /**
     * @dataProvider invalidChangesProvider
     */
    public function testItFallsBackToNullForInvalidChanges(mixed $changesInput): void
    {
        $row = ['changes' => $changesInput];
        $dto = $this->mapper->map($row);
        $this->assertNull($dto->changes);
    }

    /**
     * @return array<int, array{mixed}>
     */
    public static function invalidChangesProvider(): array
    {
        return [
            [''],
            ['not-json'],
            ['"scalar"'],
            ['123'],
            ['[1, 2, 3]'], // numeric keys
            ['{"0": "a", "b": "c"}'], // mixed keys (if key becomes int it's not string)
            [123],
        ];
    }

    public function testItAllowsEmptyAssociativeArrayChanges(): void
    {
        $row = ['changes' => '{}'];
        $dto = $this->mapper->map($row);
        $this->assertSame([], $dto->changes);
    }

    public function testItThrowsOnInvalidPersistedDateText(): void
    {
        $row = ['occurred_at' => 'invalid-date-text'];

        $this->expectException(\Exception::class);
        $this->mapper->map($row);
    }
}
