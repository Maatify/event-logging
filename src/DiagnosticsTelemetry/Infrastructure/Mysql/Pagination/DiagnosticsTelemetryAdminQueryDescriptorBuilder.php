<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\Pagination;

use DateTimeZone;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;

/** @internal */
final class DiagnosticsTelemetryAdminQueryDescriptorBuilder
{
    public function build(DiagnosticsTelemetryAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        $filtered = $this->buildFilteredWhereAndParams($request);
        $whereSql = $filtered['whereSql'];
        $params = $filtered['params'];

        $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry';
        $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry' . $whereSql;
        $dataSql = 'SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry' . $whereSql;

        return new PdoPaginationQueryDescriptor(
            totalSql: $totalSql,
            totalParams: [],
            filteredCountSql: $filteredCountSql,
            filteredCountParams: $params,
            dataSql: $dataSql,
            dataParams: $params
        );
    }

    /** @return array{whereSql: string, params: array<string, string|int|bool|null>} */
    private function buildFilteredWhereAndParams(DiagnosticsTelemetryAdminQueryRequestDTO $request): array
    {
        $conditions = [];
        $params = [];

        if ($request->actorType !== null) {
            $conditions[] = 'actor_type = :actor_type';
            $params['actor_type'] = $request->actorType;
        }
        if ($request->actorId !== null) {
            $conditions[] = 'actor_id = :actor_id';
            $params['actor_id'] = $request->actorId;
        }
        if ($request->eventKey !== null) {
            $conditions[] = 'event_key = :event_key';
            $params['event_key'] = $request->eventKey;
        }
        if ($request->severity !== null) {
            $conditions[] = 'severity = :severity';
            $params['severity'] = $request->severity;
        }
        if ($request->requestId !== null) {
            $conditions[] = 'request_id = :request_id';
            $params['request_id'] = $request->requestId;
        }
        if ($request->correlationId !== null) {
            $conditions[] = 'correlation_id = :correlation_id';
            $params['correlation_id'] = $request->correlationId;
        }
        $utc = new DateTimeZone('UTC');

        if ($request->after !== null) {
            $conditions[] = 'occurred_at >= :after';
            $params['after'] = $request->after->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }
        if ($request->before !== null) {
            $conditions[] = 'occurred_at <= :before';
            $params['before'] = $request->before->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [
            'whereSql' => $whereSql,
            'params' => $params
        ];
    }
}
