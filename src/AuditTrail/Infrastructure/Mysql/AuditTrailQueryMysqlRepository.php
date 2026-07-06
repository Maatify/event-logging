<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use PDO;
use PDOException;
use Exception;

class AuditTrailQueryMysqlRepository implements AuditTrailQueryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function find(AuditTrailQueryDTO $query): array
    {
        $conditions = [];
        $params = [];

        if ($query->actorType !== null) {
            $conditions[] = 'actor_type = :actor_type';
            $params['actor_type'] = $query->actorType;
        }

        if ($query->actorId !== null) {
            $conditions[] = 'actor_id = :actor_id';
            $params['actor_id'] = $query->actorId;
        }

        if ($query->eventKey !== null) {
            $conditions[] = 'event_key = :event_key';
            $params['event_key'] = $query->eventKey;
        }

        if ($query->correlationId !== null) {
            $conditions[] = 'correlation_id = :correlation_id';
            $params['correlation_id'] = $query->correlationId;
        }

        if ($query->after !== null) {
            $conditions[] = 'occurred_at >= :after';
            $params['after'] = $query->after->format('Y-m-d H:i:s.u');
        }

        if ($query->before !== null) {
            $conditions[] = 'occurred_at <= :before';
            $params['before'] = $query->before->format('Y-m-d H:i:s.u');
        }

        // Cursor pagination (Next Page logic for DESC order)
        if ($query->cursorOccurredAt !== null && $query->cursorId !== null) {
            $conditions[] = '(occurred_at < :cursor_at OR (occurred_at = :cursor_at AND id < :cursor_id))';
            $params['cursor_at'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_id'] = $query->cursorId;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $limit = (int) $query->limit;

        $sql = <<<SQL
            SELECT *
            FROM audit_trail
            {$whereClause}
            ORDER BY occurred_at DESC, id DESC
            LIMIT {$limit}
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $results[] = $this->mapRowToDTO($row);
            }

            return $results;
        } catch (PDOException $e) {
            throw new AuditTrailStorageException(
                message: "Failed to query audit trail: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return AuditTrailViewDTO
     */
    private function mapRowToDTO(array $row): AuditTrailViewDTO
    {
        try {
            $occurredAtString = $row['occurred_at'] ?? 'now';
            if (!is_string($occurredAtString)) {
                $occurredAtString = 'now';
            }
            $occurredAt = new DateTimeImmutable($occurredAtString, new DateTimeZone('UTC'));
        } catch (Exception) {
            // Fallback for extremely corrupted date (should not happen in strict schema)
            $occurredAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        $metadata = null;
        if (isset($row['metadata']) && is_string($row['metadata'])) {
            try {
                $decoded = json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            } catch (Exception) {
                // Swallow JSON errors on read as per spec
                $metadata = null;
            }
        }

        return new AuditTrailViewDTO(
            id: isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0,
            eventId: is_string($row['event_id'] ?? null) ? $row['event_id'] : '',
            actorType: is_string($row['actor_type'] ?? null) ? $row['actor_type'] : '',
            actorId: isset($row['actor_id']) && is_numeric($row['actor_id']) ? (int) $row['actor_id'] : null,
            eventKey: is_string($row['event_key'] ?? null) ? $row['event_key'] : '',
            entityType: is_string($row['entity_type'] ?? null) ? $row['entity_type'] : '',
            entityId: isset($row['entity_id']) && is_numeric($row['entity_id']) ? (int) $row['entity_id'] : null,
            subjectType: is_string($row['subject_type'] ?? null) ? $row['subject_type'] : null,
            subjectId: isset($row['subject_id']) && is_numeric($row['subject_id']) ? (int) $row['subject_id'] : null,
            referrerRouteName: is_string($row['referrer_route_name'] ?? null) ? $row['referrer_route_name'] : null,
            referrerPath: is_string($row['referrer_path'] ?? null) ? $row['referrer_path'] : null,
            referrerHost: is_string($row['referrer_host'] ?? null) ? $row['referrer_host'] : null,
            correlationId: is_string($row['correlation_id'] ?? null) ? $row['correlation_id'] : null,
            requestId: is_string($row['request_id'] ?? null) ? $row['request_id'] : null,
            routeName: is_string($row['route_name'] ?? null) ? $row['route_name'] : null,
            ipAddress: is_string($row['ip_address'] ?? null) ? $row['ip_address'] : null,
            userAgent: is_string($row['user_agent'] ?? null) ? $row['user_agent'] : null,
            metadata: $metadata,
            occurredAt: $occurredAt
        );
    }
}
