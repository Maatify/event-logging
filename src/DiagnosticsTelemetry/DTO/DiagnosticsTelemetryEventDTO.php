<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\DTO;

use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface;

final readonly class DiagnosticsTelemetryEventDTO implements \JsonSerializable
{
    /**
     * @param int $id
     * @param string $eventId UUID
     * @param string $eventKey
     * @param DiagnosticsTelemetrySeverityInterface $severity
     * @param DiagnosticsTelemetryContextDTO $context
     * @param int|null $durationMs
     * @param array<mixed>|null $metadata
     */
    public function __construct(
        public int $id,
        public string $eventId,
        public string $eventKey,
        public DiagnosticsTelemetrySeverityInterface $severity,
        public DiagnosticsTelemetryContextDTO $context,
        public ?int $durationMs,
        public ?array $metadata
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'eventId' => $this->eventId,
            'eventKey' => $this->eventKey,
            'severity' => $this->severity->value(),
            'context' => $this->context->jsonSerialize(),
            'durationMs' => $this->durationMs,
            'metadata' => $this->metadata,
        ];
    }

}
