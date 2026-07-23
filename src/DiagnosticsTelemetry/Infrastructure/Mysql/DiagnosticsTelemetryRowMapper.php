<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;

/**
 * @internal
 */
final class DiagnosticsTelemetryRowMapper
{
    public function __construct(
        private readonly DiagnosticsTelemetryPolicyInterface $policy
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @return DiagnosticsTelemetryEventDTO
     * @throws Exception
     */
    public function map(array $row): DiagnosticsTelemetryEventDTO
    {
        $severityStr = is_string($row['severity'] ?? null) ? $row['severity'] : 'INFO';
        $severity = $this->policy->normalizeSeverity($severityStr);

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
                // Metadata corruption in DB; treat as null
                $metadata = null;
            }
        }

        $occurredAtStr = is_string($row['occurred_at'] ?? null) ? $row['occurred_at'] : '1970-01-01 00:00:00';
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $eventId = is_string($row['event_id'] ?? null) ? $row['event_id'] : '';
        $eventKey = is_string($row['event_key'] ?? null) ? $row['event_key'] : 'unknown';

        $context = new DiagnosticsTelemetryContextDTO(
            actorType: $actorType,
            actorId: isset($row['actor_id']) && is_numeric($row['actor_id']) ? (int)$row['actor_id'] : null,
            correlationId: is_string($row['correlation_id'] ?? null) ? $row['correlation_id'] : null,
            requestId: is_string($row['request_id'] ?? null) ? $row['request_id'] : null,
            routeName: is_string($row['route_name'] ?? null) ? $row['route_name'] : null,
            ipAddress: is_string($row['ip_address'] ?? null) ? $row['ip_address'] : null,
            userAgent: is_string($row['user_agent'] ?? null) ? $row['user_agent'] : null,
            occurredAt: new DateTimeImmutable($occurredAtStr, new DateTimeZone('UTC'))
        );

        return new DiagnosticsTelemetryEventDTO(
            id: $id,
            eventId: $eventId,
            eventKey: $eventKey,
            severity: $severity,
            context: $context,
            durationMs: isset($row['duration_ms']) && is_numeric($row['duration_ms']) ? (int)$row['duration_ms'] : null,
            metadata: $metadata
        );
    }
}
