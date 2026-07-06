<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Command;

use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum;
use InvalidArgumentException;

final readonly class RecordSecuritySignalCommand
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $signalType,
        public string|SecuritySignalSeverityEnum $severity,
        public string|SecuritySignalActorTypeEnum $actorType,
        public ?int $actorId,
        public ?array $metadata = null,
        public ?string $correlationId = null,
        public ?string $requestId = null,
        public ?string $routeName = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {
        if (trim($this->signalType) === '') {
            throw new InvalidArgumentException('Security signal type must not be empty.');
        }
        if ($this->actorId !== null && $this->actorId < 1) {
            throw new InvalidArgumentException('Security signal actor id must be a positive integer when provided.');
        }
    }
}
