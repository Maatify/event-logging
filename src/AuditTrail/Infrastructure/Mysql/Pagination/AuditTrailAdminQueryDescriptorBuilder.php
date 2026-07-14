<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\Pagination;

use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;

/** @internal */
final class AuditTrailAdminQueryDescriptorBuilder
{
    public function build(AuditTrailAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        $filtered = $this->buildFilteredWhereAndParams($request);
        $whereSql = $filtered['whereSql'];
        $params = $filtered['params'];

        $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_audit_trail';
        $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_audit_trail' . $whereSql;
        $dataSql =
            'SELECT id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, '
            . 'subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, '
            . 'correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at '
            . 'FROM maa_event_logging_audit_trail'
            . $whereSql;

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
    private function buildFilteredWhereAndParams(AuditTrailAdminQueryRequestDTO $request): array
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

        if ($request->entityType !== null) {
            $conditions[] = 'entity_type = :entity_type';
            $params['entity_type'] = $request->entityType;
        }

        if ($request->entityId !== null) {
            $conditions[] = 'entity_id = :entity_id';
            $params['entity_id'] = $request->entityId;
        }

        if ($request->subjectType !== null) {
            $conditions[] = 'subject_type = :subject_type';
            $params['subject_type'] = $request->subjectType;
        }

        if ($request->subjectId !== null) {
            $conditions[] = 'subject_id = :subject_id';
            $params['subject_id'] = $request->subjectId;
        }

        if ($request->requestId !== null) {
            $conditions[] = 'request_id = :request_id';
            $params['request_id'] = $request->requestId;
        }

        if ($request->correlationId !== null) {
            $conditions[] = 'correlation_id = :correlation_id';
            $params['correlation_id'] = $request->correlationId;
        }

        if ($request->after !== null) {
            $conditions[] = 'occurred_at >= :after';
            $params['after'] = $request->after
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.u');
        }

        if ($request->before !== null) {
            $conditions[] = 'occurred_at <= :before';
            $params['before'] = $request->before
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.u');
        }

        $whereSql = $conditions === []
            ? ''
            : ' WHERE ' . implode(' AND ', $conditions);

        return [
            'whereSql' => $whereSql,
            'params' => $params,
        ];
    }
}
