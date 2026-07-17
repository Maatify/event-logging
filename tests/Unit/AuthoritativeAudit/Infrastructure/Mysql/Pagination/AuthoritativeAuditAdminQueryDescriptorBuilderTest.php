<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminQueryDescriptorBuilderTest extends TestCase
{
    public function testBuildEmptyConditions(): void
    {
        $builder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
        $request = new AuthoritativeAuditAdminQueryRequestDTO();

        $descriptor = $builder->build($request);

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);
        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log', $descriptor->filteredCountSql);
        $this->assertSame([], $descriptor->filteredCountParams);
        $this->assertSame('SELECT id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at FROM maa_event_logging_authoritative_audit_log', $descriptor->dataSql);
        $this->assertSame([], $descriptor->dataParams);
    }

    public function testBuildWithAllConditions(): void
    {
        $builder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
        $after = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('Europe/London'));
        $before = new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('Europe/London'));

        $request = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: 'event-123',
            actorType: 'user',
            actorId: 42,
            targetType: 'resource',
            targetId: 100,
            action: 'create',
            correlationId: 'corr-456',
            after: $after,
            before: $before
        );

        $descriptor = $builder->build($request);

        $expectedWhere = ' WHERE event_id = :event_id AND actor_type = :actor_type AND actor_id = :actor_id AND target_type = :target_type AND target_id = :target_id AND action = :action AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before';

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $expectedWhere, $descriptor->filteredCountSql);
        $this->assertSame('SELECT id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at FROM maa_event_logging_authoritative_audit_log' . $expectedWhere, $descriptor->dataSql);

        $expectedParams = [
            'event_id' => 'event-123',
            'actor_type' => 'user',
            'actor_id' => 42,
            'target_type' => 'resource',
            'target_id' => 100,
            'action' => 'create',
            'correlation_id' => 'corr-456',
            'after' => '2024-01-01 00:00:00.000000',
            'before' => '2024-01-02 00:00:00.000000',
        ];

        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
        $this->assertSame($expectedParams, $descriptor->dataParams);
    }
}
