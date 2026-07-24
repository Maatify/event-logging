<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\Pagination\DeliveryOperationsAdminQueryDescriptorBuilder;
use PHPUnit\Framework\TestCase;

final class DeliveryOperationsAdminQueryDescriptorBuilderTest extends TestCase
{
    private DeliveryOperationsAdminQueryDescriptorBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DeliveryOperationsAdminQueryDescriptorBuilder();
    }

    public function testItBuildsEmptyDescriptor(): void
    {
        $request = new DeliveryOperationsAdminQueryRequestDTO();
        $descriptor = $this->builder->build($request);

        $this->assertEquals('SELECT COUNT(*) FROM maa_event_logging_delivery_operations', $descriptor->totalSql);
        $this->assertEquals([], $descriptor->totalParams);

        $this->assertEquals('SELECT COUNT(*) FROM maa_event_logging_delivery_operations', $descriptor->filteredCountSql);
        $this->assertEquals([], $descriptor->filteredCountParams);

        $this->assertEquals('SELECT id, event_id, channel, operation_type, actor_type, actor_id, target_type, target_id, status, attempt_no, scheduled_at, completed_at, correlation_id, request_id, provider, provider_message_id, error_code, error_message, metadata, occurred_at FROM maa_event_logging_delivery_operations', $descriptor->dataSql);
        $this->assertEquals([], $descriptor->dataParams);
    }

    public function testItBuildsDescriptorWithAllFilters(): void
    {
        $request = new DeliveryOperationsAdminQueryRequestDTO(
            id: 5,
            eventId: 'evt-1',
            channel: 'chan-1',
            operationType: 'op-1',
            actorType: 'act-1',
            actorId: 42,
            targetType: 'tar-1',
            targetId: 43,
            status: 'stat-1',
            attemptNoMin: 1,
            attemptNoMax: 3,
            correlationId: 'cor-1',
            requestId: 'req-1',
            provider: 'prov-1',
            providerMessageId: 'pmid-1',
            errorCode: 'err-1',
            errorMessageLike: 'foo\%bar_baz\\',
            metadataFilters: ['$.foo' => 'bar', '$.baz' => 123],
            scheduledAfter: new DateTimeImmutable('2023-01-01'),
            scheduledBefore: new DateTimeImmutable('2023-01-02'),
            completedAfter: new DateTimeImmutable('2023-01-03'),
            completedBefore: new DateTimeImmutable('2023-01-04'),
            after: new DateTimeImmutable('2023-01-05'),
            before: new DateTimeImmutable('2023-01-06'),
            nullStateFilters: ['actorId' => true, 'targetId' => false]
        );

        $descriptor = $this->builder->build($request);

        $expectedWhere = ' WHERE id = :id AND event_id = :event_id AND channel = :channel AND operation_type = :operation_type AND actor_type = :actor_type AND actor_id = :actor_id AND target_type = :target_type AND target_id = :target_id AND status = :status AND attempt_no >= :attempt_no_min AND attempt_no <= :attempt_no_max AND correlation_id = :correlation_id AND request_id = :request_id AND provider = :provider AND provider_message_id = :provider_message_id AND error_code = :error_code AND error_message LIKE :error_message_like ESCAPE \'\\\\\' AND scheduled_at >= :scheduled_after AND scheduled_at <= :scheduled_before AND completed_at >= :completed_after AND completed_at <= :completed_before AND occurred_at >= :after AND occurred_at <= :before AND JSON_CONTAINS_PATH(metadata, \'one\', :meta_path_exists_1) = 1 AND JSON_CONTAINS(metadata, :meta_value_1, :meta_path_value_1) = 1 AND JSON_CONTAINS_PATH(metadata, \'one\', :meta_path_exists_2) = 1 AND JSON_CONTAINS(metadata, :meta_value_2, :meta_path_value_2) = 1 AND actor_id IS NULL AND target_id IS NOT NULL';

        $expectedParams = [
            'id' => 5,
            'event_id' => 'evt-1',
            'channel' => 'chan-1',
            'operation_type' => 'op-1',
            'actor_type' => 'act-1',
            'actor_id' => 42,
            'target_type' => 'tar-1',
            'target_id' => 43,
            'status' => 'stat-1',
            'attempt_no_min' => 1,
            'attempt_no_max' => 3,
            'correlation_id' => 'cor-1',
            'request_id' => 'req-1',
            'provider' => 'prov-1',
            'provider_message_id' => 'pmid-1',
            'error_code' => 'err-1',
            'error_message_like' => '%foo\\\\\\%bar\\_baz\\\\%', // \ -> \\, % -> \%, _ -> \_, \ -> \\
            'scheduled_after' => '2023-01-01 00:00:00.000000',
            'scheduled_before' => '2023-01-02 00:00:00.000000',
            'completed_after' => '2023-01-03 00:00:00.000000',
            'completed_before' => '2023-01-04 00:00:00.000000',
            'after' => '2023-01-05 00:00:00.000000',
            'before' => '2023-01-06 00:00:00.000000',
            'meta_path_exists_1' => '$.foo',
            'meta_path_value_1' => '$.foo',
            'meta_value_1' => '"bar"',
            'meta_path_exists_2' => '$.baz',
            'meta_path_value_2' => '$.baz',
            'meta_value_2' => '123'
        ];

        $this->assertEquals('SELECT COUNT(*) FROM maa_event_logging_delivery_operations' . $expectedWhere, $descriptor->filteredCountSql);
        $this->assertEquals($expectedParams, $descriptor->filteredCountParams);
        $this->assertEquals('SELECT id, event_id, channel, operation_type, actor_type, actor_id, target_type, target_id, status, attempt_no, scheduled_at, completed_at, correlation_id, request_id, provider, provider_message_id, error_code, error_message, metadata, occurred_at FROM maa_event_logging_delivery_operations' . $expectedWhere, $descriptor->dataSql);
        $this->assertEquals($expectedParams, $descriptor->dataParams);
    }
}
