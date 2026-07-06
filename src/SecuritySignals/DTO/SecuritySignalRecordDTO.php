<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\DTO;

use DateTimeImmutable;

final readonly class SecuritySignalRecordDTO implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $eventId,
        public string $actorType,
        public ?int $actorId,
        public string $signalType,
        public string $severity,
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
            'signalType' => $this->signalType,
            'severity' => $this->severity,
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
