<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Recorder;

use Maatify\EventLogging\AuditTrail\Command\RecordAuditTrailCommand;
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailLoggerInterface;
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailPolicyInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailRecordDTO;
use Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum;
use Maatify\EventLogging\Common\ClockInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use JsonException;
use Throwable;

class AuditTrailRecorder
{
    private readonly AuditTrailPolicyInterface $policy;

    public function __construct(
        private readonly AuditTrailLoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $fallbackLogger = null,
        ?AuditTrailPolicyInterface $policy = null
    ) {
        $this->policy = $policy ?? new AuditTrailDefaultPolicy();
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function record(
        string $eventKey,
        string|AuditTrailActorTypeEnum $actorType,
        ?int $actorId,
        string $entityType,
        ?int $entityId,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $metadata = null,
        ?string $referrerRouteName = null,
        ?string $referrerPath = null,
        ?string $referrerHost = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $routeName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
                $this->recordCommand(new RecordAuditTrailCommand(
                    eventKey: $eventKey,
                    actorType: $actorType,
                    actorId: $actorId,
                    entityType: $entityType,
                    entityId: $entityId,
                    subjectType: $subjectType,
                    subjectId: $subjectId,
                    metadata: $metadata,
                    referrerRouteName: $referrerRouteName,
                    referrerPath: $referrerPath,
                    referrerHost: $referrerHost,
                    correlationId: $correlationId,
                    requestId: $requestId,
                    routeName: $routeName,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                ));
            } catch (Throwable $e) {
            $this->reportFailure('AuditTrail logging failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function recordCommand(RecordAuditTrailCommand $command): void
    {
        try {
                $referrerPath = $command->referrerPath;
                if ($referrerPath !== null) {
                    $parsed = parse_url($referrerPath, PHP_URL_PATH);
                    $referrerPath = is_string($parsed)
                        ? $parsed
                        : explode('?', $referrerPath)[0];
                }

                $normalizedActorType = $this->policy->normalizeActorType($command->actorType);
                $metadata = $command->metadata;

                if ($metadata !== null) {
                    try {
                        $json = json_encode($metadata, JSON_THROW_ON_ERROR);
                        if (!$this->policy->validateMetadataSize($json)) {
                            if ($this->fallbackLogger) {
                                $this->fallbackLogger->warning('AuditTrail metadata exceeded limit. Dropping metadata.', [
                                    'event_key' => $command->eventKey,
                                    'size' => strlen($json),
                                ]);
                            }
                            $metadata = ['error' => 'Metadata dropped due to size limit'];
                        }
                    } catch (JsonException $e) {
                        if ($this->fallbackLogger) {
                            $this->fallbackLogger->warning('AuditTrail metadata JSON encoding failed.', [
                                'event_key' => $command->eventKey,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        $metadata = ['error' => 'Metadata dropped due to encoding error'];
                    }
                } else {
                    $metadata = [];
                }

                $recordDTO = new AuditTrailRecordDTO(
                    eventId: Uuid::uuid4()->toString(),
                    actorType: $normalizedActorType,
                    actorId: $command->actorId,
                    eventKey: $command->eventKey,
                    entityType: $command->entityType,
                    entityId: $command->entityId,
                    subjectType: $command->subjectType,
                    subjectId: $command->subjectId,
                    referrerRouteName: $command->referrerRouteName,
                    referrerPath: $referrerPath,
                    referrerHost: $command->referrerHost,
                    correlationId: $command->correlationId,
                    requestId: $command->requestId,
                    routeName: $command->routeName,
                    ipAddress: $command->ipAddress,
                    userAgent: $command->userAgent,
                    metadata: $metadata,
                    occurredAt: $this->clock->now()
                );

                try {
                    $this->logger->write($recordDTO);
                } catch (Throwable $e) {
                    if ($this->fallbackLogger) {
                        $this->fallbackLogger->error('AuditTrail logging failed', [
                            'event_key' => $command->eventKey,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $e) {
            $this->reportFailure('AuditTrail logging failed', [
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
