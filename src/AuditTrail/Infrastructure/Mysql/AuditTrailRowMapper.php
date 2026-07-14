<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;

/** @internal */
final class AuditTrailRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): AuditTrailViewDTO
    {
        try {
            $occurredAtString = $row['occurred_at'] ?? 'now';
            if (! is_string($occurredAtString)) {
                $occurredAtString = 'now';
            }
            $occurredAt = new DateTimeImmutable($occurredAtString, new DateTimeZone('UTC'));
        } catch (Exception) {
            $occurredAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        $metadata = null;
        if (isset($row['metadata']) && is_string($row['metadata'])) {
            try {
                $decoded = json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    $metadata = $decoded;
                }
            } catch (Exception) {
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
