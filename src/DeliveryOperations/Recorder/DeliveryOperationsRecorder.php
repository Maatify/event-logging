<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Recorder;

use BackedEnum;
use Maatify\EventLogging\DeliveryOperations\Command\RecordDeliveryOperationCommand;
use Maatify\SharedCommon\Contracts\ClockInterface;
use UnitEnum;
use DateTimeImmutable;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsLoggerInterface;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsPolicyInterface;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryActorTypeInterface;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryChannelEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryOperationTypeEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryStatusEnum;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;
use JsonException;

class DeliveryOperationsRecorder
{
    private readonly DeliveryOperationsPolicyInterface $policy;

    public function __construct(
        private readonly DeliveryOperationsLoggerInterface $writer,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $fallbackLogger = null,
        ?DeliveryOperationsPolicyInterface $policy = null
    ) {
        $this->policy = $policy ?? new DeliveryOperationsDefaultPolicy();
    }

    /**
     * @param array<mixed>|null $metadata
     */
    public function record(
        DeliveryChannelEnum|string $channel,
        DeliveryOperationTypeEnum|string $operationType,
        DeliveryStatusEnum|string $status,
        int $attemptNo = 0,
        DeliveryActorTypeInterface|string|null $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?DateTimeImmutable $scheduledAt = null,
        ?DateTimeImmutable $completedAt = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $provider = null,
        ?string $providerMessageId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?array $metadata = null
    ): void {
        try {
                $this->recordCommand(new RecordDeliveryOperationCommand(
                    channel: $channel,
                    operationType: $operationType,
                    status: $status,
                    attemptNo: $attemptNo,
                    actorType: $actorType,
                    actorId: $actorId,
                    targetType: $targetType,
                    targetId: $targetId,
                    scheduledAt: $scheduledAt,
                    completedAt: $completedAt,
                    correlationId: $correlationId,
                    requestId: $requestId,
                    provider: $provider,
                    providerMessageId: $providerMessageId,
                    errorCode: $errorCode,
                    errorMessage: $errorMessage,
                    metadata: $metadata
                ));
            } catch (Throwable $e) {
            $this->reportFailure('DeliveryOperations logging failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function recordCommand(RecordDeliveryOperationCommand $command): void
    {
        try {
            $channelStr = $this->enumToString($command->channel);
            $operationTypeStr = $this->enumToString($command->operationType);
            $statusStr = $this->enumToString($command->status);

            $normalizedActorType = null;
            if ($command->actorType !== null) {
                $normalizedActorType = $this->policy->normalizeActorType($command->actorType);
            }

            $channelStr = $this->truncateString($channelStr, 32);
            $operationTypeStr = $this->truncateString($operationTypeStr, 64);
            $statusStr = $this->truncateString($statusStr, 32);
            $targetType = $this->truncate($command->targetType, 64);
            $correlationId = $this->truncate($command->correlationId, 36);
            $requestId = $this->truncate($command->requestId, 64);
            $provider = $this->truncate($command->provider, 64);
            $providerMessageId = $this->truncate($command->providerMessageId, 128);
            $errorCode = $this->truncate($command->errorCode, 64);
            $metadata = $command->metadata;

            if ($metadata !== null) {
                try {
                    $json = json_encode($metadata, JSON_THROW_ON_ERROR);
                    if (!$this->policy->validateMetadataSize($json)) {
                        if ($this->fallbackLogger) {
                            $this->fallbackLogger->warning('DeliveryOperations metadata too large', ['size' => strlen($json)]);
                        }
                        $metadata = ['error' => 'Metadata dropped: too large'];
                    }
                } catch (JsonException $e) {
                    if ($this->fallbackLogger) {
                        $this->fallbackLogger->warning('DeliveryOperations metadata encoding failed', ['error' => $e->getMessage()]);
                    }
                    $metadata = ['error' => 'Metadata dropped: encoding error'];
                }
            } else {
                $metadata = [];
            }

            $dto = new DeliveryOperationRecordDTO(
                eventId: Uuid::uuid4()->toString(),
                channel: $channelStr,
                operationType: $operationTypeStr,
                actorType: $normalizedActorType,
                actorId: $command->actorId,
                targetType: $targetType,
                targetId: $command->targetId,
                status: $statusStr,
                attemptNo: $command->attemptNo,
                scheduledAt: $command->scheduledAt,
                completedAt: $command->completedAt,
                correlationId: $correlationId,
                requestId: $requestId,
                provider: $provider,
                providerMessageId: $providerMessageId,
                errorCode: $errorCode,
                errorMessage: $command->errorMessage,
                metadata: $metadata,
                occurredAt: $this->clock->now()
            );

            $this->writer->log($dto);
        } catch (Throwable $e) {
            $this->reportFailure('DeliveryOperations logging failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function enumToString(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }
        if (is_object($value) && method_exists($value, 'value')) {
            /** @var mixed $val */
            $val = $value->value();
            if (is_string($val) || is_int($val)) {
                return (string) $val;
            }
        }

        if (is_string($value) || is_int($value)) {
            return (string) $value;
        }

        return '';
    }

    private function truncate(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }
        return $this->truncateString($value, $limit);
    }

    private function truncateString(string $value, int $limit): string
    {
        if (mb_strlen($value) > $limit) {
            return mb_substr($value, 0, $limit);
        }
        return $value;
    }
    /**
     * @param array<string, mixed> $context
     */
    private function reportFailure(string $message, array $context = []): void
    {
        if ($this->fallbackLogger === null) {
            return;
        }

        try {
            $this->fallbackLogger->error($message, $context);
        } catch (Throwable) {
            // Fail-open logging must not throw if fallback logging fails.
        }
    }

}
