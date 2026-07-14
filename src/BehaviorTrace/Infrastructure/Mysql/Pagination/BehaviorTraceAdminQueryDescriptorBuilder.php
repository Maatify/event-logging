<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\Pagination;

use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;

/** @internal */
final class BehaviorTraceAdminQueryDescriptorBuilder
{
    public function build(BehaviorTraceAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        $filter = $this->buildFilteredWhereAndParams($request);
        $whereClause = $filter['whereSql'];
        $params = $filter['params'];

        $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_behavior_trace';

        $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_behavior_trace' . $whereClause;

        $dataSql = 'SELECT id, event_id, actor_type, actor_id, action, entity_type, entity_id, '
            . 'metadata, correlation_id, request_id, route_name, ip_address, '
            . 'user_agent, occurred_at '
            . 'FROM maa_event_logging_behavior_trace' . $whereClause;

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
    private function buildFilteredWhereAndParams(BehaviorTraceAdminQueryRequestDTO $request): array
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

        if ($request->action !== null) {
            $where[] = 'action = :action';
            $params['action'] = $request->action;
        }

        if ($request->entityType !== null) {
            $where[] = 'entity_type = :entity_type';
            $params['entity_type'] = $request->entityType;
        }

        if ($request->entityId !== null) {
            $where[] = 'entity_id = :entity_id';
            $params['entity_id'] = $request->entityId;
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

        $whereClause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        return [
            'whereSql' => $whereClause,
            'params' => $params,
        ];
    }
}
