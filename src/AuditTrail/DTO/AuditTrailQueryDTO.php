<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\DTO;

use DateTimeImmutable;

final readonly class AuditTrailQueryDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $eventKey = null,
        public ?string $correlationId = null,
        public ?DateTimeImmutable $after = null,
        public ?DateTimeImmutable $before = null,
        public ?DateTimeImmutable $cursorOccurredAt = null,
        public ?int $cursorId = null,
        public int $limit = 50
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'eventKey' => $this->eventKey,
            'correlationId' => $this->correlationId,
            'after' => $this->after?->format(DATE_ATOM),
            'before' => $this->before?->format(DATE_ATOM),
            'cursorOccurredAt' => $this->cursorOccurredAt?->format(DATE_ATOM),
            'cursorId' => $this->cursorId,
            'limit' => $this->limit,
        ];
    }

}
