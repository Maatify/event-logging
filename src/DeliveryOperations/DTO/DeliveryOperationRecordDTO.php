<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\DTO;

use DateTimeImmutable;

final readonly class DeliveryOperationRecordDTO implements \JsonSerializable
{
    /**
     * @param string $eventId
     * @param string $channel
     * @param string $operationType
     * @param string|null $actorType
     * @param int|null $actorId
     * @param string|null $targetType
     * @param int|null $targetId
     * @param string $status
     * @param int $attemptNo
     * @param DateTimeImmutable|null $scheduledAt
     * @param DateTimeImmutable|null $completedAt
     * @param string|null $correlationId
     * @param string|null $requestId
     * @param string|null $provider
     * @param string|null $providerMessageId
     * @param string|null $errorCode
     * @param string|null $errorMessage
     * @param array<mixed>|null $metadata
     * @param DateTimeImmutable $occurredAt
     */
    public function __construct(
        public string $eventId,
        public string $channel,
        public string $operationType,
        public ?string $actorType,
        public ?int $actorId,
        public ?string $targetType,
        public ?int $targetId,
        public string $status,
        public int $attemptNo,
        public ?DateTimeImmutable $scheduledAt,
        public ?DateTimeImmutable $completedAt,
        public ?string $correlationId,
        public ?string $requestId,
        public ?string $provider,
        public ?string $providerMessageId,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?array $metadata,
        public DateTimeImmutable $occurredAt
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'eventId' => $this->eventId,
            'channel' => $this->channel,
            'operationType' => $this->operationType,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'status' => $this->status,
            'attemptNo' => $this->attemptNo,
            'scheduledAt' => $this->scheduledAt?->format(DATE_ATOM),
            'completedAt' => $this->completedAt?->format(DATE_ATOM),
            'correlationId' => $this->correlationId,
            'requestId' => $this->requestId,
            'provider' => $this->provider,
            'providerMessageId' => $this->providerMessageId,
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'metadata' => $this->metadata,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

}
