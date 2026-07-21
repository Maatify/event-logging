<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;

/** @internal */
final class AuthoritativeAuditRowMapper
{
    /**
     * @param array<string, mixed> $row
     * @throws Exception
     */
    public function map(array $row): AuthoritativeAuditViewDTO
    {
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $eventId = is_string($row['event_id'] ?? null) ? $row['event_id'] : '';
        $actorType = is_string($row['actor_type'] ?? null) ? $row['actor_type'] : null;
        $actorId = isset($row['actor_id']) && is_numeric($row['actor_id']) ? (int) $row['actor_id'] : null;
        $action = is_string($row['action'] ?? null) ? $row['action'] : '';
        $targetType = is_string($row['target_type'] ?? null) ? $row['target_type'] : null;
        $targetId = isset($row['target_id']) && is_numeric($row['target_id']) ? (int) $row['target_id'] : null;
        $ipAddress = is_string($row['ip_address'] ?? null) ? $row['ip_address'] : null;
        $userAgent = is_string($row['user_agent'] ?? null) ? $row['user_agent'] : null;
        $correlationId = is_string($row['correlation_id'] ?? null) ? $row['correlation_id'] : null;

        $changes = null;
        if (isset($row['changes']) && is_string($row['changes']) && $row['changes'] !== '') {
            try {
                $decoded = json_decode($row['changes'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $isAssociative = false;
                    foreach (array_keys($decoded) as $key) {
                        if (!is_string($key)) {
                            $isAssociative = false;
                            break;
                        }
                        $isAssociative = true;
                    }
                    if ($isAssociative || empty($decoded)) {
                        $changes = $decoded;
                    }
                }
            } catch (JsonException $e) {
                $changes = null;
            }
        }

        $occurredAtString = is_string($row['occurred_at'] ?? null) ? $row['occurred_at'] : '1970-01-01 00:00:00';
        $occurredAt = new DateTimeImmutable($occurredAtString, new DateTimeZone('UTC'));

        return new AuthoritativeAuditViewDTO(
            $id,
            $eventId,
            $actorType,
            $actorId,
            $action,
            $targetType,
            $targetId,
            $ipAddress,
            $userAgent,
            $correlationId,
            $changes,
            $occurredAt
        );
    }
}
