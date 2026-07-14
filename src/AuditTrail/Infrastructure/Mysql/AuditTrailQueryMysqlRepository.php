<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Infrastructure\Mysql;

use Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use PDO;
use PDOException;
use Throwable;

class AuditTrailQueryMysqlRepository implements AuditTrailQueryInterface
{
    private AuditTrailRowMapper $mapper;

    public function __construct(
        private readonly PDO $pdo
    ) {
        $this->mapper = new AuditTrailRowMapper();
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


        if ($query->entityType !== null) {
            $conditions[] = 'entity_type = :entity_type';
            $params['entity_type'] = $query->entityType;
        }

        if ($query->entityId !== null) {
            $conditions[] = 'entity_id = :entity_id';
            $params['entity_id'] = $query->entityId;
        }

        if ($query->subjectType !== null) {
            $conditions[] = 'subject_type = :subject_type';
            $params['subject_type'] = $query->subjectType;
        }

        if ($query->subjectId !== null) {
            $conditions[] = 'subject_id = :subject_id';
            $params['subject_id'] = $query->subjectId;
        }

        if ($query->requestId !== null) {
            $conditions[] = 'request_id = :request_id';
            $params['request_id'] = $query->requestId;
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
            $conditions[] = '(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))';
            $params['cursor_at_before'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_at_equal'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_id'] = $query->cursorId;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $limit = (int) $query->limit;

        $sql = <<<SQL
            SELECT *
            FROM maa_event_logging_audit_trail
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
                /** @var array<string, mixed> $row */
                $results[] = $this->mapper->map($row);
            }

            return $results;
        } catch (PDOException $e) {
            throw new AuditTrailStorageException(
                message: "Failed to query audit trail: " . $e->getMessage(),
                previous: $e
            );
        } catch (Throwable $e) {
            throw new AuditTrailStorageException(
                message: "Failed to map audit trail row: " . $e->getMessage(),
                previous: $e
            );
        }
    }
}
