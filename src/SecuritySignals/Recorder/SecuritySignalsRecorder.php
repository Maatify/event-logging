<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Recorder;

use Maatify\EventLogging\SecuritySignals\Command\RecordSecuritySignalCommand;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsLoggerInterface;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsPolicyInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use JsonException;
use Throwable;

class SecuritySignalsRecorder
{
    private readonly SecuritySignalsPolicyInterface $policy;

    public function __construct(
        private readonly SecuritySignalsLoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $fallbackLogger = null,
        ?SecuritySignalsPolicyInterface $policy = null
    ) {
        $this->policy = $policy ?? new SecuritySignalsDefaultPolicy();
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function record(
        string $signalType,
        string|SecuritySignalSeverityEnum $severity,
        string|SecuritySignalActorTypeEnum $actorType,
        ?int $actorId,
        ?array $metadata = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $routeName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
                $this->recordCommand(new RecordSecuritySignalCommand(
                    signalType: $signalType,
                    severity: $severity,
                    actorType: $actorType,
                    actorId: $actorId,
                    metadata: $metadata,
                    correlationId: $correlationId,
                    requestId: $requestId,
                    routeName: $routeName,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                ));
            } catch (Throwable $e) {
            $this->reportFailure('SecuritySignals logging failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function recordCommand(RecordSecuritySignalCommand $command): void
    {
        try {
            $normalizedActorType = $this->policy->normalizeActorType($command->actorType);
            $normalizedSeverity = $this->policy->normalizeSeverity($command->severity);

            $safeSignalType = substr($command->signalType, 0, 100);
            $safeActorType = substr($normalizedActorType, 0, 32);
            $safeSeverity = substr($normalizedSeverity, 0, 16);
            $safeCorrelationId = $command->correlationId ? substr($command->correlationId, 0, 36) : null;
            $safeRequestId = $command->requestId ? substr($command->requestId, 0, 64) : null;
            $safeRouteName = $command->routeName ? substr($command->routeName, 0, 255) : null;
            $safeIpAddress = $command->ipAddress ? substr($command->ipAddress, 0, 45) : null;
            $safeUserAgent = $command->userAgent ? substr($command->userAgent, 0, 512) : null;
            $metadata = $command->metadata;

            if ($metadata !== null) {
                try {
                    $json = json_encode($metadata, JSON_THROW_ON_ERROR);
                    if (!$this->policy->validateMetadataSize($json)) {
                        if ($this->fallbackLogger) {
                            $this->fallbackLogger->warning('SecuritySignals metadata exceeded limit. Dropping metadata.', [
                                'signal_type' => $safeSignalType,
                                'size' => strlen($json),
                            ]);
                        }
                        $metadata = ['error' => 'Metadata dropped due to size limit'];
                    }
                } catch (JsonException $e) {
                    if ($this->fallbackLogger) {
                        $this->fallbackLogger->warning('SecuritySignals metadata JSON encoding failed.', [
                            'signal_type' => $safeSignalType,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $metadata = ['error' => 'Metadata dropped due to encoding error'];
                }
            } else {
                $metadata = [];
            }

            $recordDTO = new SecuritySignalRecordDTO(
                eventId: Uuid::uuid4()->toString(),
                actorType: $safeActorType,
                actorId: $command->actorId,
                signalType: $safeSignalType,
                severity: $safeSeverity,
                correlationId: $safeCorrelationId,
                requestId: $safeRequestId,
                routeName: $safeRouteName,
                ipAddress: $safeIpAddress,
                userAgent: $safeUserAgent,
                metadata: $metadata,
                occurredAt: $this->clock->now()
            );

            $this->logger->write($recordDTO);
        } catch (Throwable $e) {
            $this->reportFailure('SecuritySignals logging failed', [
                'signal_type' => substr($command->signalType, 0, 100),
                'exception' => $e->getMessage(),
            ]);
        }
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
