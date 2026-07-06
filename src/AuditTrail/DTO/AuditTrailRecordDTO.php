<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\DTO;

use DateTimeImmutable;

final readonly class AuditTrailRecordDTO implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $eventId,
        public string $actorType,
        public ?int $actorId,
        public string $eventKey,
        public string $entityType,
        public ?int $entityId,
        public ?string $subjectType,
        public ?int $subjectId,
        public ?string $referrerRouteName,
        public ?string $referrerPath,
        public ?string $referrerHost,
        public ?string $correlationId,
        public ?string $requestId,
        public ?string $routeName,
        public ?string $ipAddress,
        public ?string $userAgent,
        public array $metadata,
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
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'eventKey' => $this->eventKey,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
            'referrerRouteName' => $this->referrerRouteName,
            'referrerPath' => $this->referrerPath,
            'referrerHost' => $this->referrerHost,
            'correlationId' => $this->correlationId,
            'requestId' => $this->requestId,
            'routeName' => $this->routeName,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'metadata' => $this->metadata,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

}
