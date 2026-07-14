<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\Pagination\BehaviorTraceAdminQueryDescriptorBuilder;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use PHPUnit\Framework\TestCase;

final class BehaviorTraceAdminQueryDescriptorBuilderTest extends TestCase
{
    public function testBuildsExpectedSqlAndParams(): void
    {
        $descriptor = (new BehaviorTraceAdminQueryDescriptorBuilder())->build(new BehaviorTraceAdminQueryRequestDTO(
            actorType: 'user',
            actorId: 10,
            action: 'view',
            entityType: 'document',
            entityId: 20,
            requestId: 'req',
            correlationId: 'corr',
            after: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Africa/Cairo')),
            before: new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('Africa/Cairo')),
        ));

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_behavior_trace', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);
        $this->assertSame(
            'SELECT COUNT(*) FROM maa_event_logging_behavior_trace WHERE actor_type = :actor_type AND actor_id = :actor_id AND action = :action AND entity_type = :entity_type AND entity_id = :entity_id AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before',
            $descriptor->filteredCountSql,
        );
        $this->assertSame(
            'SELECT id, event_id, actor_type, actor_id, action, entity_type, entity_id, metadata, correlation_id, request_id, route_name, ip_address, user_agent, occurred_at FROM maa_event_logging_behavior_trace WHERE actor_type = :actor_type AND actor_id = :actor_id AND action = :action AND entity_type = :entity_type AND entity_id = :entity_id AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before',
            $descriptor->dataSql,
        );
        $this->assertStringNotContainsString('SELECT *', $descriptor->dataSql);
        $this->assertStringNotContainsString('ORDER BY', $descriptor->dataSql);
        $this->assertStringNotContainsString('LIMIT', $descriptor->dataSql);
        $this->assertStringNotContainsString('OFFSET', $descriptor->dataSql);

        $expectedParams = [
            'actor_type' => 'user',
            'actor_id' => 10,
            'action' => 'view',
            'entity_type' => 'document',
            'entity_id' => 20,
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
        $descriptor = (new BehaviorTraceAdminQueryDescriptorBuilder())->build(new BehaviorTraceAdminQueryRequestDTO());

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_behavior_trace', $descriptor->filteredCountSql);
        $this->assertSame(
            'SELECT id, event_id, actor_type, actor_id, action, entity_type, entity_id, metadata, correlation_id, request_id, route_name, ip_address, user_agent, occurred_at FROM maa_event_logging_behavior_trace',
            $descriptor->dataSql,
        );
        $this->assertSame([], $descriptor->filteredCountParams);
        $this->assertSame([], $descriptor->dataParams);
    }

    public function testInvalidPersistenceDescriptorContractsAreRejectedAtBoundary(): void
    {
        $this->expectException(InvalidPaginationQueryException::class);
        $this->expectExceptionMessage('Invalid pagination parameter key.');

        new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM maa_event_logging_behavior_trace',
            totalParams: [':bad' => 'value'],
            filteredCountSql: 'SELECT COUNT(*) FROM maa_event_logging_behavior_trace',
            filteredCountParams: [],
            dataSql: 'SELECT id FROM maa_event_logging_behavior_trace',
            dataParams: [],
        );
    }
}
