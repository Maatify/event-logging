<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql;

use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsLoggerInterface;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use PDO;
use PDOException;
use JsonException;
use DateTimeImmutable;

class DeliveryOperationsLoggerMysqlRepository implements DeliveryOperationsLoggerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function log(DeliveryOperationRecordDTO $dto): void
    {
        $sql = <<<SQL
            INSERT INTO maa_event_logging_delivery_operations (
                event_id,
                channel,
                operation_type,
                actor_type,
                actor_id,
                target_type,
                target_id,
                status,
                attempt_no,
                scheduled_at,
                completed_at,
                correlation_id,
                request_id,
                provider,
                provider_message_id,
                error_code,
                error_message,
                metadata,
                occurred_at
            ) VALUES (
                :event_id,
                :channel,
                :operation_type,
                :actor_type,
                :actor_id,
                :target_type,
                :target_id,
                :status,
                :attempt_no,
                :scheduled_at,
                :completed_at,
                :correlation_id,
                :request_id,
                :provider,
                :provider_message_id,
                :error_code,
                :error_message,
                :metadata,
                :occurred_at
            )
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);

            $metadataJson = $dto->metadata ? json_encode($dto->metadata, JSON_THROW_ON_ERROR) : '{}';

            $occurredAt = $this->formatDate($dto->occurredAt);
            $scheduledAt = $this->formatDate($dto->scheduledAt);
            $completedAt = $this->formatDate($dto->completedAt);

            $stmt->execute([
                ':event_id' => $dto->eventId,
                ':channel' => $dto->channel,
                ':operation_type' => $dto->operationType,
                ':actor_type' => $dto->actorType,
                ':actor_id' => $dto->actorId,
                ':target_type' => $dto->targetType,
                ':target_id' => $dto->targetId,
                ':status' => $dto->status,
                ':attempt_no' => $dto->attemptNo,
                ':scheduled_at' => $scheduledAt,
                ':completed_at' => $completedAt,
                ':correlation_id' => $dto->correlationId,
                ':request_id' => $dto->requestId,
                ':provider' => $dto->provider,
                ':provider_message_id' => $dto->providerMessageId,
                ':error_code' => $dto->errorCode,
                ':error_message' => $dto->errorMessage,
                ':metadata' => $metadataJson,
                ':occurred_at' => $occurredAt,
            ]);
        } catch (PDOException $e) {
            throw new DeliveryOperationsStorageException('Database write failed: ' . $e->getMessage(), 0, $e);
        } catch (JsonException $e) {
            throw new DeliveryOperationsStorageException('Metadata encoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function formatDate(?DateTimeImmutable $date): ?string
    {
        if ($date === null) {
            return null;
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
