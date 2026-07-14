<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePolicyInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;

/** @internal */
final class BehaviorTraceRowMapper
{
    public function __construct(
        private BehaviorTracePolicyInterface $policy,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws Exception
     */
    public function map(array $row): BehaviorTraceEventDTO
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
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $eventId = is_string($row['event_id'] ?? null) ? $row['event_id'] : '';
        $action = is_string($row['action'] ?? null) ? $row['action'] : 'unknown';

        $context = new BehaviorTraceContextDTO(
            actorType: $actorType,
            actorId: isset($row['actor_id']) && is_numeric($row['actor_id']) ? (int) $row['actor_id'] : null,
            correlationId: is_string($row['correlation_id'] ?? null) ? $row['correlation_id'] : null,
            requestId: is_string($row['request_id'] ?? null) ? $row['request_id'] : null,
            routeName: is_string($row['route_name'] ?? null) ? $row['route_name'] : null,
            ipAddress: is_string($row['ip_address'] ?? null) ? $row['ip_address'] : null,
            userAgent: is_string($row['user_agent'] ?? null) ? $row['user_agent'] : null,
            occurredAt: new DateTimeImmutable($occurredAtStr, new DateTimeZone('UTC')),
        );

        return new BehaviorTraceEventDTO(
            id: $id,
            eventId: $eventId,
            action: $action,
            entityType: is_string($row['entity_type'] ?? null) ? $row['entity_type'] : null,
            entityId: isset($row['entity_id']) && is_numeric($row['entity_id']) ? (int) $row['entity_id'] : null,
            context: $context,
            metadata: $metadata,
        );
    }
}
