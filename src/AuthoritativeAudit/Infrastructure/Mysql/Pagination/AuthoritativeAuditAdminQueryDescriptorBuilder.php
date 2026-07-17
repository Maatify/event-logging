<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use DateTimeZone;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;

/** @internal */
final class AuthoritativeAuditAdminQueryDescriptorBuilder
{
    public function build(AuthoritativeAuditAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        $filtered = $this->buildFilteredWhereAndParams($request);
        $whereSql = $filtered['whereSql'];
        $params = $filtered['params'];

        $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log';
        $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $whereSql;
        $dataSql = 'SELECT id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at FROM maa_event_logging_authoritative_audit_log' . $whereSql;

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
    private function buildFilteredWhereAndParams(AuthoritativeAuditAdminQueryRequestDTO $request): array
    {
        $conditions = [];
        $params = [];

        if ($request->eventId !== null) {
            $conditions[] = 'event_id = :event_id';
            $params['event_id'] = $request->eventId;
        }
        if ($request->actorType !== null) {
            $conditions[] = 'actor_type = :actor_type';
            $params['actor_type'] = $request->actorType;
        }
        if ($request->actorId !== null) {
            $conditions[] = 'actor_id = :actor_id';
            $params['actor_id'] = $request->actorId;
        }
        if ($request->targetType !== null) {
            $conditions[] = 'target_type = :target_type';
            $params['target_type'] = $request->targetType;
        }
        if ($request->targetId !== null) {
            $conditions[] = 'target_id = :target_id';
            $params['target_id'] = $request->targetId;
        }
        if ($request->action !== null) {
            $conditions[] = 'action = :action';
            $params['action'] = $request->action;
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
