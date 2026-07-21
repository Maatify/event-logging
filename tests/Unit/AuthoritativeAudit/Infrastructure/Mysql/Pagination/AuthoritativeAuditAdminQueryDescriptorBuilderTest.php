<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder
 */
final class AuthoritativeAuditAdminQueryDescriptorBuilderTest extends TestCase
{
    private AuthoritativeAuditAdminQueryDescriptorBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
    }

    public function testItBuildsNoFilterDescriptor(): void
    {
        $request = new AuthoritativeAuditAdminQueryRequestDTO();
        $descriptor = $this->builder->build($request);

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log', $descriptor->filteredCountSql);
        $this->assertSame([], $descriptor->filteredCountParams);

        $this->assertSame('SELECT id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at FROM maa_event_logging_authoritative_audit_log', $descriptor->dataSql);
        $this->assertSame([], $descriptor->dataParams);
    }

    public function testItBuildsDescriptorWithAllFilters(): void
    {
        $after = new DateTimeImmutable('2023-01-01T10:00:00+02:00'); // Non-UTC
        $before = new DateTimeImmutable('2023-01-02T10:00:00+02:00'); // Non-UTC

        $request = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: 'event-1',
            actorType: 'sys',
            actorId: 1,
            targetType: 'file',
            targetId: 2,
            action: 'delete',
            correlationId: 'corr-1',
            after: $after,
            before: $before
        );

        $descriptor = $this->builder->build($request);

        $expectedWhereSql = ' WHERE event_id = :event_id AND actor_type = :actor_type AND actor_id = :actor_id AND target_type = :target_type AND target_id = :target_id AND action = :action AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before';

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $expectedWhereSql, $descriptor->filteredCountSql);
        $this->assertSame('SELECT id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at FROM maa_event_logging_authoritative_audit_log' . $expectedWhereSql, $descriptor->dataSql);

        $expectedParams = [
            'event_id' => 'event-1',
            'actor_type' => 'sys',
            'actor_id' => 1,
            'target_type' => 'file',
            'target_id' => 2,
            'action' => 'delete',
            'correlation_id' => 'corr-1',
            'after' => '2023-01-01 08:00:00.000000',
            'before' => '2023-01-02 08:00:00.000000',
        ];

        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
        $this->assertSame($expectedParams, $descriptor->dataParams);
    }

    public function testItBuildsDescriptorWithEqualDateBoundaries(): void
    {
        $date = new DateTimeImmutable('2023-01-01T10:00:00Z');
        $request = new AuthoritativeAuditAdminQueryRequestDTO(after: $date, before: $date);

        $descriptor = $this->builder->build($request);

        $expectedWhereSql = ' WHERE occurred_at >= :after AND occurred_at <= :before';

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $expectedWhereSql, $descriptor->filteredCountSql);

        $expectedParams = [
            'after' => '2023-01-01 10:00:00.000000',
            'before' => '2023-01-01 10:00:00.000000',
        ];

        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
    }

    public function testItBuildsDescriptorWithEveryFilterIndependently(): void
    {
        $filters = [
            ['eventId', 'test-event-id', 'event_id = :event_id', ['event_id' => 'test-event-id']],
            ['actorType', 'admin', 'actor_type = :actor_type', ['actor_type' => 'admin']],
            ['actorId', 42, 'actor_id = :actor_id', ['actor_id' => 42]],
            ['targetType', 'user', 'target_type = :target_type', ['target_type' => 'user']],
            ['targetId', 99, 'target_id = :target_id', ['target_id' => 99]],
            ['action', 'login', 'action = :action', ['action' => 'login']],
            ['correlationId', 'corr-123', 'correlation_id = :correlation_id', ['correlation_id' => 'corr-123']],
        ];

        foreach ($filters as [$field, $value, $expectedCondition, $expectedParams]) {
            $request = new AuthoritativeAuditAdminQueryRequestDTO(
                eventId: $field === 'eventId' ? $value : null,
                actorType: $field === 'actorType' ? $value : null,
                actorId: $field === 'actorId' ? $value : null,
                targetType: $field === 'targetType' ? $value : null,
                targetId: $field === 'targetId' ? $value : null,
                action: $field === 'action' ? $value : null,
                correlationId: $field === 'correlationId' ? $value : null
            );

            $descriptor = $this->builder->build($request);

            $expectedWhereSql = ' WHERE ' . $expectedCondition;
            $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $expectedWhereSql, $descriptor->filteredCountSql, "Failed on field: $field");
            $this->assertSame($expectedParams, $descriptor->filteredCountParams, "Failed on field: $field");
        }

        // Independent dates
        $date = new DateTimeImmutable('2023-01-01T10:00:00Z');

        $requestAfter = new AuthoritativeAuditAdminQueryRequestDTO(after: $date);
        $descriptorAfter = $this->builder->build($requestAfter);
        $expectedWhereSqlAfter = ' WHERE occurred_at >= :after';
        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $expectedWhereSqlAfter, $descriptorAfter->filteredCountSql);
        $this->assertSame(['after' => '2023-01-01 10:00:00.000000'], $descriptorAfter->filteredCountParams);

        $requestBefore = new AuthoritativeAuditAdminQueryRequestDTO(before: $date);
        $descriptorBefore = $this->builder->build($requestBefore);
        $expectedWhereSqlBefore = ' WHERE occurred_at <= :before';
        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $expectedWhereSqlBefore, $descriptorBefore->filteredCountSql);
        $this->assertSame(['before' => '2023-01-01 10:00:00.000000'], $descriptorBefore->filteredCountParams);
    }
}
