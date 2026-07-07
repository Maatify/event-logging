<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

final readonly class AuthoritativeAuditQueryDTO implements \JsonSerializable
{
    public function __construct(
        public ?\DateTimeImmutable $after = null,
        public ?\DateTimeImmutable $before = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?string $action = null,
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
            'action' => $this->action,
            'correlationId' => $this->correlationId,
            'cursorOccurredAt' => $this->cursorOccurredAt?->format(DATE_ATOM),
            'cursorId' => $this->cursorId,
            'limit' => $this->limit,
        ];
    }
}
