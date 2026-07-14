<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\Pagination\AuditTrailAdminQueryDescriptorBuilder;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use PHPUnit\Framework\TestCase;

final class AuditTrailAdminQueryDescriptorBuilderTest extends TestCase
{
    public function testBuildsExpectedSqlAndParams(): void
    {
        $descriptor = (new AuditTrailAdminQueryDescriptorBuilder())->build(new AuditTrailAdminQueryRequestDTO(
            actorType: 'user',
            actorId: 10,
            eventKey: 'view',
            entityType: 'document',
            entityId: 20,
            subjectType: 'account',
            subjectId: 30,
            requestId: 'req',
            correlationId: 'corr',
            after: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Africa/Cairo')),
            before: new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('Africa/Cairo'))
        ));

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_audit_trail', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);
        $this->assertStringStartsWith('SELECT COUNT(*) FROM maa_event_logging_audit_trail WHERE actor_type = :actor_type', $descriptor->filteredCountSql);
        $this->assertStringStartsWith('SELECT id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at FROM maa_event_logging_audit_trail WHERE actor_type = :actor_type', $descriptor->dataSql);
        $this->assertStringNotContainsString('SELECT *', $descriptor->dataSql);
        $this->assertStringNotContainsString('ORDER BY', $descriptor->dataSql);
        $this->assertStringNotContainsString('LIMIT', $descriptor->dataSql);
        $this->assertStringNotContainsString('OFFSET', $descriptor->dataSql);

        $expectedParams = [
            'actor_type' => 'user',
            'actor_id' => 10,
            'event_key' => 'view',
            'entity_type' => 'document',
            'entity_id' => 20,
            'subject_type' => 'account',
            'subject_id' => 30,
            'request_id' => 'req',
            'correlation_id' => 'corr',
            'after' => '2024-01-01 10:00:00.000000',
            'before' => '2024-01-01 11:00:00.000000',
        ];
        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
        $this->assertSame($expectedParams, $descriptor->dataParams);

        foreach (array_keys($descriptor->dataParams) as $key) {
            $this->assertStringStartsNotWith(':', $key);
            $this->assertStringStartsNotWith('__pagination_', $key);
        }
    }

    public function testNoFilterDescriptorUsesNoWhereClause(): void
    {
        $descriptor = (new AuditTrailAdminQueryDescriptorBuilder())->build(new AuditTrailAdminQueryRequestDTO());

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_audit_trail', $descriptor->filteredCountSql);
        $this->assertSame(
            'SELECT id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at FROM maa_event_logging_audit_trail',
            $descriptor->dataSql
        );
        $this->assertSame([], $descriptor->filteredCountParams);
        $this->assertSame([], $descriptor->dataParams);
    }

    public function testInvalidPersistenceDescriptorContractsAreRejectedAtBoundary(): void
    {
        $this->expectException(InvalidPaginationQueryException::class);
        $this->expectExceptionMessage('Invalid pagination parameter key.');

        new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM maa_event_logging_audit_trail',
            totalParams: [':bad' => 'value'],
            filteredCountSql: 'SELECT COUNT(*) FROM maa_event_logging_audit_trail',
            filteredCountParams: [],
            dataSql: 'SELECT id FROM maa_event_logging_audit_trail',
            dataParams: []
        );
    }
}
