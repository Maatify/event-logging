<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql;

use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryQueryInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryCursorDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryQueryDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryDefaultPolicy;
use PDO;
use PDOException;
use Exception;

class DiagnosticsTelemetryQueryMysqlRepository implements DiagnosticsTelemetryQueryInterface
{
    private const TABLE_NAME = 'maa_event_logging_diagnostics_telemetry';

    private readonly DiagnosticsTelemetryRowMapper $mapper;

    public function __construct(
        private readonly PDO $pdo,
        ?DiagnosticsTelemetryPolicyInterface $policy = null
    ) {
        $effectivePolicy = $policy ?? new DiagnosticsTelemetryDefaultPolicy();
        $this->mapper = new DiagnosticsTelemetryRowMapper($effectivePolicy);
    }

    /**
     * @return array<DiagnosticsTelemetryEventDTO>
     */
    public function find(DiagnosticsTelemetryQueryDTO $query): array
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

        if ($query->severity !== null) {
            $conditions[] = 'severity = :severity';
            $params['severity'] = $query->severity;
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

        if ($query->cursorOccurredAt !== null && $query->cursorId !== null) {
            $conditions[] = '(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))';
            $params['cursor_at_before'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_at_equal'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_id'] = $query->cursorId;
        }

        $whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $limit = max(1, $query->limit);
        $sql = sprintf('SELECT * FROM %s %s ORDER BY occurred_at DESC, id DESC LIMIT %d', self::TABLE_NAME, $whereClause, $limit);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                /** @var array<string, mixed> $row */
                $results[] = $this->mapper->map($row);
            }

            return $results;
        } catch (PDOException $e) {
            throw new DiagnosticsTelemetryStorageException('Failed to query DiagnosticsTelemetry records: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new DiagnosticsTelemetryStorageException('Failed to map DiagnosticsTelemetry row: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return iterable<DiagnosticsTelemetryEventDTO>
     */
    public function read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100): iterable
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE 1=1',
            self::TABLE_NAME
        );

        $params = [];

        if ($cursor) {
            $sql .= ' AND (occurred_at > :last_occurred_at OR (occurred_at = :last_occurred_at_eq AND id > :last_id))';
            $params[':last_occurred_at'] = $cursor->lastOccurredAt->format('Y-m-d H:i:s.u');
            $params[':last_occurred_at_eq'] = $cursor->lastOccurredAt->format('Y-m-d H:i:s.u');
            $params[':last_id'] = $cursor->lastId;
        }

        $sql .= ' ORDER BY occurred_at ASC, id ASC LIMIT :limit';

        try {
            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                /** @var array<string, mixed> $row */
                yield $this->mapper->map($row);
            }

        } catch (PDOException $e) {
            throw new DiagnosticsTelemetryStorageException('Failed to read telemetry logs: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new DiagnosticsTelemetryStorageException('Failed to map telemetry row: ' . $e->getMessage(), 0, $e);
        }
    }
}
