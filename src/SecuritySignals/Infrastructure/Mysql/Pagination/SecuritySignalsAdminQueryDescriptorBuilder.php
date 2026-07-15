<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\Pagination;

use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;

/** @internal */
final class SecuritySignalsAdminQueryDescriptorBuilder
{
    public function build(SecuritySignalsAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        $filter = $this->buildFilteredWhereAndParams($request);
        $whereClause = $filter['whereSql'];
        $params = $filter['params'];

        $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_security_signals';
        $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_security_signals' . $whereClause;
        $dataSql = 'SELECT id, event_id, actor_type, actor_id, signal_type, severity, '
            . 'correlation_id, request_id, route_name, ip_address, user_agent, metadata, '
            . 'occurred_at FROM maa_event_logging_security_signals' . $whereClause;

        return new PdoPaginationQueryDescriptor(
            totalSql: $totalSql,
            totalParams: [],
            filteredCountSql: $filteredCountSql,
            filteredCountParams: $params,
            dataSql: $dataSql,
            dataParams: $params,
        );
    }

    /**
     * @return array{
     *     whereSql: string,
     *     params: array<string, string|int|bool|null>
     * }
     */
    private function buildFilteredWhereAndParams(SecuritySignalsAdminQueryRequestDTO $request): array
    {
        $where = [];
        $params = [];

        if ($request->actorType !== null) {
            $where[] = 'actor_type = :actor_type';
            $params['actor_type'] = $request->actorType;
        }

        if ($request->actorId !== null) {
            $where[] = 'actor_id = :actor_id';
            $params['actor_id'] = $request->actorId;
        }

        if ($request->signalType !== null) {
            $where[] = 'signal_type = :signal_type';
            $params['signal_type'] = $request->signalType;
        }

        if ($request->severity !== null) {
            $where[] = 'severity = :severity';
            $params['severity'] = $request->severity;
        }

        if ($request->requestId !== null) {
            $where[] = 'request_id = :request_id';
            $params['request_id'] = $request->requestId;
        }

        if ($request->correlationId !== null) {
            $where[] = 'correlation_id = :correlation_id';
            $params['correlation_id'] = $request->correlationId;
        }

        $utc = new DateTimeZone('UTC');
        if ($request->after !== null) {
            $where[] = 'occurred_at >= :after';
            $params['after'] = $request->after->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }

        if ($request->before !== null) {
            $where[] = 'occurred_at <= :before';
            $params['before'] = $request->before->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }

        return [
            'whereSql' => $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
            'params' => $params,
        ];
    }
}
