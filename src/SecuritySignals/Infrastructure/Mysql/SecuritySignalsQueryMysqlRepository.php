<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql;

use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsQueryInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use PDO;
use PDOException;
use Throwable;

final class SecuritySignalsQueryMysqlRepository implements SecuritySignalsQueryInterface
{
    private const TABLE_NAME = 'maa_event_logging_security_signals';

    private SecuritySignalsRowMapper $mapper;

    public function __construct(private readonly PDO $pdo) {}

    /** @return array<SecuritySignalsViewDTO> */
    public function find(SecuritySignalsQueryDTO $query): array
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

        if ($query->signalType !== null) {
            $conditions[] = 'signal_type = :signal_type';
            $params['signal_type'] = $query->signalType;
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

        if ($query->after !== null) { $conditions[] = 'occurred_at >= :after'; $params['after'] = $query->after->format('Y-m-d H:i:s.u'); }
        if ($query->before !== null) { $conditions[] = 'occurred_at <= :before'; $params['before'] = $query->before->format('Y-m-d H:i:s.u'); }
        if ($query->cursorOccurredAt !== null && $query->cursorId !== null) {
            $conditions[] = '(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))';
            $cursorAt = $query->cursorOccurredAt->format('Y-m-d H:i:s.u');
            $params['cursor_at_before'] = $cursorAt;
            $params['cursor_at_equal'] = $cursorAt;
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
                $results[] = $this->mapper()->map($row);
            }

            return $results;
        } catch (PDOException $e) {
            throw new SecuritySignalsStorageException('Failed to query SecuritySignals records: ' . $e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new SecuritySignalsStorageException('Failed to map SecuritySignals row: ' . $e->getMessage(), 0, $e);
        }
    }

    private function mapper(): SecuritySignalsRowMapper
    {
        if (! isset($this->mapper)) {
            $this->mapper = new SecuritySignalsRowMapper();
        }

        return $this->mapper;
    }
}
