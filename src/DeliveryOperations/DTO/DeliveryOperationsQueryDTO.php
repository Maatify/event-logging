<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\DTO;

final readonly class DeliveryOperationsQueryDTO implements \JsonSerializable
{
    public function __construct(
        public ?\DateTimeImmutable $after = null,
        public ?\DateTimeImmutable $before = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?string $channel = null,
        public ?string $operationType = null,
        public ?string $status = null,
        public ?string $requestId = null,
        public ?string $correlationId = null,
        public ?\DateTimeImmutable $cursorOccurredAt = null,
        public ?int $cursorId = null,
        public int $limit = 50
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): mixed
    {
        return [
            'after' => $this->after?->format(DATE_ATOM),
            'before' => $this->before?->format(DATE_ATOM),
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'channel' => $this->channel,
            'operationType' => $this->operationType,
            'status' => $this->status,
            'requestId' => $this->requestId,
            'correlationId' => $this->correlationId,
            'cursorOccurredAt' => $this->cursorOccurredAt?->format(DATE_ATOM),
            'cursorId' => $this->cursorId,
            'limit' => $this->limit,
        ];
    }
}
