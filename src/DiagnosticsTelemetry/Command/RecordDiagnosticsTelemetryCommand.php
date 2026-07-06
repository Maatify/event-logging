<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Command;

use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface;
use InvalidArgumentException;

final readonly class RecordDiagnosticsTelemetryCommand
{
    /**
     * @param array<mixed>|null $metadata
     */
    public function __construct(
        public string $eventKey,
        public DiagnosticsTelemetrySeverityInterface|string $severity,
        public DiagnosticsTelemetryActorTypeInterface|string $actorType,
        public ?int $actorId = null,
        public ?string $correlationId = null,
        public ?string $requestId = null,
        public ?string $routeName = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?int $durationMs = null,
        public ?array $metadata = null
    ) {
        if (trim($this->eventKey) === '') {
            throw new InvalidArgumentException('Diagnostics telemetry event key must not be empty.');
        }
        if ($this->actorId !== null && $this->actorId < 1) {
            throw new InvalidArgumentException('Diagnostics telemetry actor id must be a positive integer when provided.');
        }
    }
}
