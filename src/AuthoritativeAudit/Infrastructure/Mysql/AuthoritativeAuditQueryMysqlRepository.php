<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use PDO;
use PDOException;
use Throwable;

final class AuthoritativeAuditQueryMysqlRepository implements AuthoritativeAuditQueryInterface
{
    private const TABLE_NAME = 'maa_event_logging_authoritative_audit_log';

    public function __construct(private readonly PDO $pdo) {}

    /** @return array<AuthoritativeAuditViewDTO> */
    public function find(AuthoritativeAuditQueryDTO $query): array
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

        if ($query->targetType !== null) {
            $conditions[] = 'target_type = :target_type';
            $params['target_type'] = $query->targetType;
        }

        if ($query->targetId !== null) {
            $conditions[] = 'target_id = :target_id';
            $params['target_id'] = $query->targetId;
        }

        if ($query->action !== null) {
            $conditions[] = 'action = :action';
            $params['action'] = $query->action;
        }

        if ($query->correlationId !== null) {
            $conditions[] = 'correlation_id = :correlation_id';
            $params['correlation_id'] = $query->correlationId;
        }

        if ($query->after !== null) { $conditions[] = 'occurred_at >= :after'; $params['after'] = $query->after->format('Y-m-d H:i:s.u'); }
        if ($query->before !== null) { $conditions[] = 'occurred_at <= :before'; $params['before'] = $query->before->format('Y-m-d H:i:s.u'); }
        if ($query->cursorOccurredAt !== null && $query->cursorId !== null) {
            $conditions[] = '(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))';
            $params['cursor_at_before'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_at_equal'] = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_id'] = $query->cursorId;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $limit = max(1, $query->limit);
        $sql = sprintf('SELECT * FROM %s %s ORDER BY occurred_at DESC, id DESC LIMIT %d', self::TABLE_NAME, $where, $limit);

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
                $results[] = $this->mapRowToDTO($row);
            }

            return $results;
        } catch (PDOException $e) {
            throw new AuthoritativeAuditStorageException('Failed to query AuthoritativeAudit records: ' . $e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new AuthoritativeAuditStorageException('Failed to map AuthoritativeAudit row: ' . $e->getMessage(), 0, $e);
        }
    }

    /** @param array<string, mixed> $row */
    private function mapRowToDTO(array $row): AuthoritativeAuditViewDTO
    {
        return new AuthoritativeAuditViewDTO(
            id: self::intValue($row, 'id') ?? 0,
            eventId: self::stringValue($row, 'event_id') ?? '',
            actorType: self::stringValue($row, 'actor_type'),
            actorId: self::intValue($row, 'actor_id'),
            action: self::stringValue($row, 'action') ?? '',
            targetType: self::stringValue($row, 'target_type'),
            targetId: self::intValue($row, 'target_id'),
            ipAddress: self::stringValue($row, 'ip_address'),
            userAgent: self::stringValue($row, 'user_agent'),
            correlationId: self::stringValue($row, 'correlation_id'),
            changes: self::jsonArray($row, 'changes'),
            occurredAt: self::dateValue($row, 'occurred_at')
        );
    }


    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): ?string
    {
        return is_string($row[$key] ?? null) ? $row[$key] : null;
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): ?int
    {
        return isset($row[$key]) && is_numeric($row[$key]) ? (int) $row[$key] : null;
    }

    /** @param array<string, mixed> $row */
    private static function dateValue(array $row, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(is_string($row[$key] ?? null) ? $row[$key] : '1970-01-01 00:00:00', new DateTimeZone('UTC'));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function jsonArray(array $row, string $key): ?array
    {
        if (!isset($row[$key]) || !is_string($row[$key]) || $row[$key] === '') {
            return null;
        }

        try {
            $decoded = json_decode($row[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        foreach (array_keys($decoded) as $decodedKey) {
            if (!is_string($decodedKey)) {
                return null;
            }
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
