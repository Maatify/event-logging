<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Recorder;

use Maatify\EventLogging\DiagnosticsTelemetry\Command\RecordDiagnosticsTelemetryCommand;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryLoggerInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use JsonException;
use Throwable;

class DiagnosticsTelemetryRecorder
{
    private readonly DiagnosticsTelemetryPolicyInterface $policy;

    public function __construct(
        private readonly DiagnosticsTelemetryLoggerInterface $writer,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $fallbackLogger = null,
        ?DiagnosticsTelemetryPolicyInterface $policy = null
    ) {
        $this->policy = $policy ?? new DiagnosticsTelemetryDefaultPolicy();
    }

    /**
     * @param array<mixed>|null $metadata
     */
    public function record(
        string $eventKey,
        DiagnosticsTelemetrySeverityInterface|string $severity,
        DiagnosticsTelemetryActorTypeInterface|string $actorType,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $routeName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $durationMs = null,
        ?array $metadata = null
    ): void {
        try {
                $this->recordCommand(new RecordDiagnosticsTelemetryCommand(
                    eventKey: $eventKey,
                    severity: $severity,
                    actorType: $actorType,
                    actorId: $actorId,
                    correlationId: $correlationId,
                    requestId: $requestId,
                    routeName: $routeName,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    durationMs: $durationMs,
                    metadata: $metadata
                ));
            } catch (Throwable $e) {
            $this->reportFailure('Telemetry logging failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function recordCommand(RecordDiagnosticsTelemetryCommand $command): void
    {
        try {
                $eventKey = $this->truncateString($command->eventKey, 255);
                $correlationId = $this->truncate($command->correlationId, 36);
                $requestId = $this->truncate($command->requestId, 64);
                $routeName = $this->truncate($command->routeName, 255);
                $ipAddress = $this->truncate($command->ipAddress, 45);
                $userAgent = $this->truncate($command->userAgent, 512);
                $durationMs = $command->durationMs;

                if ($durationMs !== null && $durationMs < 0) {
                    $durationMs = 0;
                }

                $normalizedSeverity = $this->policy->normalizeSeverity($command->severity);
                $normalizedActorType = $this->policy->normalizeActorType($command->actorType);
                $metadata = $command->metadata;

                if ($metadata !== null) {
                    try {
                        $json = json_encode($metadata, JSON_THROW_ON_ERROR);
                        if (!$this->policy->validateMetadataSize($json)) {
                            if ($this->fallbackLogger) {
                                $this->fallbackLogger->warning('Telemetry metadata exceeded limit. Dropping metadata.', [
                                    'event_key' => $eventKey,
                                    'size' => strlen($json)
                                ]);
                            }
                            $metadata = ['error' => 'Metadata dropped due to size limit'];
                        }
                    } catch (JsonException $e) {
                        if ($this->fallbackLogger) {
                            $this->fallbackLogger->warning('Telemetry metadata JSON encoding failed.', [
                                'event_key' => $eventKey,
                                'error' => $e->getMessage()
                            ]);
                        }
                        $metadata = ['error' => 'Metadata dropped due to encoding error'];
                    }
                }

                $context = new DiagnosticsTelemetryContextDTO(
                    actorType: $normalizedActorType,
                    actorId: $command->actorId,
                    correlationId: $correlationId,
                    requestId: $requestId,
                    routeName: $routeName,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    occurredAt: $this->clock->now()
                );

                $dto = new DiagnosticsTelemetryEventDTO(
                    eventId: Uuid::uuid4()->toString(),
                    eventKey: $eventKey,
                    severity: $normalizedSeverity,
                    context: $context,
                    durationMs: $durationMs,
                    metadata: $metadata
                );

                try {
                    $this->writer->write($dto);
                } catch (Throwable $e) {
                    if ($this->fallbackLogger) {
                        $this->fallbackLogger->error('Telemetry logging failed', [
                            'exception' => $e->getMessage(),
                            'event_key' => $eventKey,
                        ]);
                    }
                }
            } catch (Throwable $e) {
            $this->reportFailure('Telemetry logging failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function truncate(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }
        return $this->truncateString($value, $limit);
    }

    private function truncateString(string $value, int $limit): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') > $limit) {
                return mb_substr($value, 0, $limit, 'UTF-8');
            }
            return $value;
        }

        if (strlen($value) > $limit) {
            return substr($value, 0, $limit);
        }
        return $value;
    }
    /**
     * @param array<string, mixed> $context
     */
    private function reportFailure(string $message, array $context = []): void
    {
        if ($this->fallbackLogger === null) {
            return;
        }

        try {
            $this->fallbackLogger->error($message, $context);
        } catch (Throwable) {
            // Fail-open logging must not throw if fallback logging fails.
        }
    }

}
