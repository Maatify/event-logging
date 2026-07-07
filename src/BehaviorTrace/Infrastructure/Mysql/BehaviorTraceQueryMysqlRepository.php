<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql;

use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePolicyInterface;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceQueryInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceCursorDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceDefaultPolicy;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Exception;
use JsonException;

class BehaviorTraceQueryMysqlRepository implements BehaviorTraceQueryInterface
{
    private const TABLE_NAME = 'maa_event_logging_behavior_trace';

    private readonly BehaviorTracePolicyInterface $policy;

    public function __construct(
        private readonly PDO $pdo,
        ?BehaviorTracePolicyInterface $policy = null
    ) {
        $this->policy = $policy ?? new BehaviorTraceDefaultPolicy();
    }


    /**
     * @return array<BehaviorTraceEventDTO>
     */
    public function find(BehaviorTraceQueryDTO $query): array
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

        if ($query->entityType !== null) {
            $conditions[] = 'entity_type = :entity_type';
            $params['entity_type'] = $query->entityType;
        }

        if ($query->entityId !== null) {
            $conditions[] = 'entity_id = :entity_id';
            $params['entity_id'] = $query->entityId;
        }

        if ($query->action !== null) {
            $conditions[] = 'action = :action';
            $params['action'] = $query->action;
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
            $conditions[] = '(occurred_at < :cursor_at OR (occurred_at = :cursor_at AND id < :cursor_id))';
            $params['cursor_at'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_id'] = $query->cursorId;
        }

        $whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $limit = max(1, $query->limit);
        $sql = sprintf('SELECT * FROM %s %s ORDER BY occurred_at DESC, id DESC LIMIT %d', self::TABLE_NAME, $whereClause, $limit);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn (array $row): BehaviorTraceEventDTO => $this->mapRowToDTO($row), $rows);
        } catch (PDOException $e) {
            throw new BehaviorTraceStorageException('Failed to query BehaviorTrace records: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new BehaviorTraceStorageException('Failed to map BehaviorTrace row: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return iterable<BehaviorTraceEventDTO>
     */
    public function read(?BehaviorTraceCursorDTO $cursor, int $limit = 100): iterable
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
                yield $this->mapRowToDTO($row);
            }

        } catch (PDOException $e) {
            throw new BehaviorTraceStorageException('Failed to read behavior trace: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new BehaviorTraceStorageException('Failed to map behavior trace row: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return BehaviorTraceEventDTO
     * @throws Exception
     */
    private function mapRowToDTO(array $row): BehaviorTraceEventDTO
    {
        $actorTypeStr = is_string($row['actor_type'] ?? null) ? $row['actor_type'] : 'ANONYMOUS';
        $actorType = $this->policy->normalizeActorType($actorTypeStr);

        $metadata = null;
        if (isset($row['metadata']) && is_string($row['metadata']) && $row['metadata'] !== '') {
            try {
                $decoded = json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            } catch (JsonException) {
                $metadata = null;
            }
        }

        $occurredAtStr = is_string($row['occurred_at'] ?? null) ? $row['occurred_at'] : '1970-01-01 00:00:00';
        $eventId = is_string($row['event_id'] ?? null) ? $row['event_id'] : '';
        $action = is_string($row['action'] ?? null) ? $row['action'] : 'unknown';

        $context = new BehaviorTraceContextDTO(
            actorType: $actorType,
            actorId: isset($row['actor_id']) && is_numeric($row['actor_id']) ? (int)$row['actor_id'] : null,
            correlationId: is_string($row['correlation_id'] ?? null) ? $row['correlation_id'] : null,
            requestId: is_string($row['request_id'] ?? null) ? $row['request_id'] : null,
            routeName: is_string($row['route_name'] ?? null) ? $row['route_name'] : null,
            ipAddress: is_string($row['ip_address'] ?? null) ? $row['ip_address'] : null,
            userAgent: is_string($row['user_agent'] ?? null) ? $row['user_agent'] : null,
            occurredAt: new DateTimeImmutable($occurredAtStr, new DateTimeZone('UTC'))
        );

        return new BehaviorTraceEventDTO(
            eventId: $eventId,
            action: $action,
            entityType: is_string($row['entity_type'] ?? null) ? $row['entity_type'] : null,
            entityId: isset($row['entity_id']) && is_numeric($row['entity_id']) ? (int)$row['entity_id'] : null,
            context: $context,
            metadata: $metadata
        );
    }
}
