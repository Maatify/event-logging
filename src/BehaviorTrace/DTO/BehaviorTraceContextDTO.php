<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\DTO;

use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;
use DateTimeImmutable;

final readonly class BehaviorTraceContextDTO implements \JsonSerializable
{
    public function __construct(
        public BehaviorTraceActorTypeInterface $actorType,
        public ?int $actorId,
        public ?string $correlationId,
        public ?string $requestId,
        public ?string $routeName,
        public ?string $ipAddress,
        public ?string $userAgent,
        public DateTimeImmutable $occurredAt
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'actorType' => $this->actorType->value(),
            'actorId' => $this->actorId,
            'correlationId' => $this->correlationId,
            'requestId' => $this->requestId,
            'routeName' => $this->routeName,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

}
