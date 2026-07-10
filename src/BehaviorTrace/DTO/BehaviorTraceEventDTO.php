<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\DTO;

final readonly class BehaviorTraceEventDTO implements \JsonSerializable
{
    /**
     * @param int $id
     * @param string $eventId UUID
     * @param string $action
     * @param string|null $entityType
     * @param int|null $entityId
     * @param BehaviorTraceContextDTO $context
     * @param array<mixed>|null $metadata
     */
    public function __construct(
        public int $id,
        public string $eventId,
        public string $action,
        public ?string $entityType,
        public ?int $entityId,
        public BehaviorTraceContextDTO $context,
        public ?array $metadata
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'eventId' => $this->eventId,
            'action' => $this->action,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'context' => $this->context->jsonSerialize(),
            'metadata' => $this->metadata,
        ];
    }

}
