<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

final readonly class AuthoritativeAuditViewDTO implements \JsonSerializable
{
    /** @param array<string, mixed>|null $changes */
    public function __construct(
        public int $id,
        public string $eventId,
        public ?string $actorType,
        public ?int $actorId,
        public string $action,
        public ?string $targetType,
        public ?int $targetId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $correlationId,
        public ?array $changes,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'eventId' => $this->eventId,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'action' => $this->action,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'correlationId' => $this->correlationId,
            'changes' => $this->changes,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
