<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\Pagination\DiagnosticsTelemetryAdminQueryDescriptorBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\Pagination\DiagnosticsTelemetryAdminQueryDescriptorBuilder
 */
final class DiagnosticsTelemetryAdminQueryDescriptorBuilderTest extends TestCase
{
    private DiagnosticsTelemetryAdminQueryDescriptorBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DiagnosticsTelemetryAdminQueryDescriptorBuilder();
    }

    public function testItIsInternalAndFinal(): void
    {
        $reflector = new ReflectionClass(DiagnosticsTelemetryAdminQueryDescriptorBuilder::class);

        $this->assertTrue($reflector->isFinal());
        $this->assertStringContainsString('@internal', (string) $reflector->getDocComment());
    }

    public function testItBuildsNoFilterDescriptor(): void
    {
        $request = new DiagnosticsTelemetryAdminQueryRequestDTO();
        $descriptor = $this->builder->build($request);

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry', $descriptor->filteredCountSql);
        $this->assertSame([], $descriptor->filteredCountParams);

        $this->assertSame('SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry', $descriptor->dataSql);
        $this->assertSame([], $descriptor->dataParams);
    }

    public function testItBuildsDescriptorWithAllFilters(): void
    {
        $after = new DateTimeImmutable('2023-01-01T10:00:00.123456+02:00'); // Non-UTC with microseconds
        $before = new DateTimeImmutable('2023-01-02T10:00:00.654321+02:00'); // Non-UTC with microseconds

        $request = new DiagnosticsTelemetryAdminQueryRequestDTO(
            actorType: 'sys',
            actorId: 1,
            eventKey: 'login',
            severity: 'INFO',
            requestId: 'req-1',
            correlationId: 'corr-1',
            after: $after,
            before: $before
        );

        $descriptor = $this->builder->build($request);

        $expectedWhereSql = ' WHERE actor_type = :actor_type AND actor_id = :actor_id AND event_key = :event_key AND severity = :severity AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before';

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSql, $descriptor->filteredCountSql);
        $this->assertSame('SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSql, $descriptor->dataSql);

        $expectedParams = [
            'actor_type' => 'sys',
            'actor_id' => 1,
            'event_key' => 'login',
            'severity' => 'INFO',
            'request_id' => 'req-1',
            'correlation_id' => 'corr-1',
            'after' => '2023-01-01 08:00:00.123456',
            'before' => '2023-01-02 08:00:00.654321',
        ];

        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
        $this->assertSame($expectedParams, $descriptor->dataParams);
    }

    public function testItBuildsDescriptorWithEqualDateBoundaries(): void
    {
        $date = new DateTimeImmutable('2023-01-01T10:00:00Z');
        $request = new DiagnosticsTelemetryAdminQueryRequestDTO(after: $date, before: $date);

        $descriptor = $this->builder->build($request);

        $expectedWhereSql = ' WHERE occurred_at >= :after AND occurred_at <= :before';

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSql, $descriptor->filteredCountSql);
        $this->assertSame('SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSql, $descriptor->dataSql);

        $expectedParams = [
            'after' => '2023-01-01 10:00:00.000000',
            'before' => '2023-01-01 10:00:00.000000',
        ];

        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
        $this->assertSame($expectedParams, $descriptor->dataParams);
    }

    public function testItBuildsDescriptorWithEveryFilterIndependently(): void
    {
        $filters = [
            ['actorType', 'admin', 'actor_type = :actor_type', ['actor_type' => 'admin']],
            ['actorId', 42, 'actor_id = :actor_id', ['actor_id' => 42]],
            ['eventKey', 'login', 'event_key = :event_key', ['event_key' => 'login']],
            ['severity', 'ERROR', 'severity = :severity', ['severity' => 'ERROR']],
            ['requestId', 'req-1', 'request_id = :request_id', ['request_id' => 'req-1']],
            ['correlationId', 'corr-123', 'correlation_id = :correlation_id', ['correlation_id' => 'corr-123']],
        ];

        foreach ($filters as [$field, $value, $expectedCondition, $expectedParams]) {
            $request = new DiagnosticsTelemetryAdminQueryRequestDTO(
                actorType: $field === 'actorType' ? $value : null,
                actorId: $field === 'actorId' ? $value : null,
                eventKey: $field === 'eventKey' ? $value : null,
                severity: $field === 'severity' ? $value : null,
                requestId: $field === 'requestId' ? $value : null,
                correlationId: $field === 'correlationId' ? $value : null
            );

            $descriptor = $this->builder->build($request);

            $expectedWhereSql = ' WHERE ' . $expectedCondition;
            $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSql, $descriptor->filteredCountSql, "Failed on field: $field");
            $this->assertSame($expectedParams, $descriptor->filteredCountParams, "Failed on field: $field");
            $this->assertSame('SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSql, $descriptor->dataSql, "Failed on field: $field");
            $this->assertSame($expectedParams, $descriptor->dataParams, "Failed on field: $field");
        }

        // Independent dates
        $date = new DateTimeImmutable('2023-01-01T10:00:00Z');

        $requestAfter = new DiagnosticsTelemetryAdminQueryRequestDTO(after: $date);
        $descriptorAfter = $this->builder->build($requestAfter);
        $expectedWhereSqlAfter = ' WHERE occurred_at >= :after';
        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSqlAfter, $descriptorAfter->filteredCountSql);
        $this->assertSame('SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSqlAfter, $descriptorAfter->dataSql);
        $this->assertSame(['after' => '2023-01-01 10:00:00.000000'], $descriptorAfter->filteredCountParams);
        $this->assertSame(['after' => '2023-01-01 10:00:00.000000'], $descriptorAfter->dataParams);

        $requestBefore = new DiagnosticsTelemetryAdminQueryRequestDTO(before: $date);
        $descriptorBefore = $this->builder->build($requestBefore);
        $expectedWhereSqlBefore = ' WHERE occurred_at <= :before';
        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSqlBefore, $descriptorBefore->filteredCountSql);
        $this->assertSame('SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry' . $expectedWhereSqlBefore, $descriptorBefore->dataSql);
        $this->assertSame(['before' => '2023-01-01 10:00:00.000000'], $descriptorBefore->filteredCountParams);
        $this->assertSame(['before' => '2023-01-01 10:00:00.000000'], $descriptorBefore->dataParams);
    }
}
