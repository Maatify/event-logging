<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use DateTimeImmutable;

final readonly class AuthoritativeAuditOutboxWriteDTO implements \JsonSerializable
{
    /**
     * @param string $eventId
     * @param string $actorType
     * @param int|null $actorId
     * @param string $action
     * @param string $targetType
     * @param int|null $targetId
     * @param string $riskLevel
     * @param array<mixed> $payload
     * @param string $correlationId
     * @param DateTimeImmutable $createdAt
     */
    public function __construct(
        public string $eventId,
        public string $actorType,
        public ?int $actorId,
        public string $action,
        public string $targetType,
        public ?int $targetId,
        public string $riskLevel,
        public array $payload,
        public string $correlationId,
        public DateTimeImmutable $createdAt
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'eventId' => $this->eventId,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'action' => $this->action,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'riskLevel' => $this->riskLevel,
            'payload' => $this->payload,
            'correlationId' => $this->correlationId,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }

}
