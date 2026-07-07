<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\DTO;

final readonly class SecuritySignalsQueryDTO implements \JsonSerializable
{
    public function __construct(
        public ?\DateTimeImmutable $after = null,
        public ?\DateTimeImmutable $before = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $signalType = null,
        public ?string $severity = null,
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
            'signalType' => $this->signalType,
            'severity' => $this->severity,
            'requestId' => $this->requestId,
            'correlationId' => $this->correlationId,
            'cursorOccurredAt' => $this->cursorOccurredAt?->format(DATE_ATOM),
            'cursorId' => $this->cursorId,
            'limit' => $this->limit,
        ];
    }
}
