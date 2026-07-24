<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsViewDTO;

/**
 * @internal
 */
final class DeliveryOperationsRowMapper
{
    /**
     * @param array<string, mixed> $row
     * @return DeliveryOperationsViewDTO
     * @throws Exception
     */
    public function map(array $row): DeliveryOperationsViewDTO
    {
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $eventId = is_string($row['event_id'] ?? null) ? $row['event_id'] : '';
        $channel = is_string($row['channel'] ?? null) ? $row['channel'] : '';
        $operationType = is_string($row['operation_type'] ?? null) ? $row['operation_type'] : '';
        $status = is_string($row['status'] ?? null) ? $row['status'] : '';

        $actorType = is_string($row['actor_type'] ?? null) ? $row['actor_type'] : null;
        $targetType = is_string($row['target_type'] ?? null) ? $row['target_type'] : null;
        $correlationId = is_string($row['correlation_id'] ?? null) ? $row['correlation_id'] : null;
        $requestId = is_string($row['request_id'] ?? null) ? $row['request_id'] : null;
        $provider = is_string($row['provider'] ?? null) ? $row['provider'] : null;
        $providerMessageId = is_string($row['provider_message_id'] ?? null) ? $row['provider_message_id'] : null;
        $errorCode = is_string($row['error_code'] ?? null) ? $row['error_code'] : null;
        $errorMessage = is_string($row['error_message'] ?? null) ? $row['error_message'] : null;

        $actorId = isset($row['actor_id']) && is_numeric($row['actor_id']) ? (int) $row['actor_id'] : null;
        $targetId = isset($row['target_id']) && is_numeric($row['target_id']) ? (int) $row['target_id'] : null;

        $attemptNo = isset($row['attempt_no']) && is_numeric($row['attempt_no']) ? (int) $row['attempt_no'] : 0;

        $utc = new DateTimeZone('UTC');

        $scheduledAt = null;
        if (isset($row['scheduled_at']) && is_string($row['scheduled_at'])) {
            $scheduledAt = new DateTimeImmutable($row['scheduled_at'], $utc);
        }

        $completedAt = null;
        if (isset($row['completed_at']) && is_string($row['completed_at'])) {
            $completedAt = new DateTimeImmutable($row['completed_at'], $utc);
        }

        $occurredAtStr = is_string($row['occurred_at'] ?? null) ? $row['occurred_at'] : '1970-01-01 00:00:00';
        $occurredAt = new DateTimeImmutable($occurredAtStr, $utc);

        /** @var array<string, mixed>|null $metadata */
        $metadata = null;
        if (isset($row['metadata']) && is_string($row['metadata']) && $row['metadata'] !== '') {
            try {
                $decoded = json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && array_is_list($decoded) === false || $decoded === []) {
                    /** @var array<string, mixed> $decoded */
                    $metadata = $decoded;
                }
            } catch (JsonException) {
                // Mapping failure or corruption -> fallback to null.
            }
        }

        return new DeliveryOperationsViewDTO(
            id: $id,
            eventId: $eventId,
            channel: $channel,
            operationType: $operationType,
            actorType: $actorType,
            actorId: $actorId,
            targetType: $targetType,
            targetId: $targetId,
            status: $status,
            attemptNo: $attemptNo,
            scheduledAt: $scheduledAt,
            completedAt: $completedAt,
            correlationId: $correlationId,
            requestId: $requestId,
            provider: $provider,
            providerMessageId: $providerMessageId,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            metadata: $metadata,
            occurredAt: $occurredAt
        );
    }
}
