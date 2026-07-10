<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Recorder;

use Maatify\EventLogging\BehaviorTrace\Command\RecordBehaviorTraceCommand;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePolicyInterface;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceWriterInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use JsonException;
use Throwable;

class BehaviorTraceRecorder
{
    private readonly BehaviorTracePolicyInterface $policy;

    public function __construct(
        private readonly BehaviorTraceWriterInterface $writer,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $fallbackLogger = null,
        ?BehaviorTracePolicyInterface $policy = null
    ) {
        $this->policy = $policy ?? new BehaviorTraceDefaultPolicy();
    }

    /**
     * @param array<mixed>|null $metadata
     */
    public function record(
        string $action,
        BehaviorTraceActorTypeInterface|string $actorType,
        ?int $actorId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $routeName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): void {
        try {
                $this->recordCommand(new RecordBehaviorTraceCommand(
                    action: $action,
                    actorType: $actorType,
                    actorId: $actorId,
                    entityType: $entityType,
                    entityId: $entityId,
                    correlationId: $correlationId,
                    requestId: $requestId,
                    routeName: $routeName,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    metadata: $metadata
                ));
            } catch (Throwable $e) {
            $this->reportFailure('Behavior trace logging failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function recordCommand(RecordBehaviorTraceCommand $command): void
    {
        try {
                $action = $this->truncateString($command->action, 128);
                $entityType = $this->truncate($command->entityType, 64);
                $correlationId = $this->truncate($command->correlationId, 36);
                $requestId = $this->truncate($command->requestId, 64);
                $routeName = $this->truncate($command->routeName, 255);
                $ipAddress = $this->truncate($command->ipAddress, 45);
                $userAgent = $this->truncate($command->userAgent, 512);
                $normalizedActorType = $this->policy->normalizeActorType($command->actorType);
                $metadata = $command->metadata;

                if ($metadata !== null) {
                    try {
                        $json = json_encode($metadata, JSON_THROW_ON_ERROR);
                        if (!$this->policy->validateMetadataSize($json)) {
                            if ($this->fallbackLogger) {
                                $this->fallbackLogger->warning('Behavior trace metadata exceeded limit. Dropping metadata.', [
                                    'action' => $action,
                                    'size' => strlen($json)
                                ]);
                            }
                            $metadata = ['error' => 'Metadata dropped due to size limit'];
                        }
                    } catch (JsonException $e) {
                        if ($this->fallbackLogger) {
                            $this->fallbackLogger->warning('Behavior trace metadata JSON encoding failed.', [
                                'action' => $action,
                                'error' => $e->getMessage()
                            ]);
                        }
                        $metadata = ['error' => 'Metadata dropped due to encoding error'];
                    }
                }

                $context = new BehaviorTraceContextDTO(
                    actorType: $normalizedActorType,
                    actorId: $command->actorId,
                    correlationId: $correlationId,
                    requestId: $requestId,
                    routeName: $routeName,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    occurredAt: $this->clock->now()
                );

                $dto = new BehaviorTraceEventDTO(
                    id: 0,
                    eventId: Uuid::uuid4()->toString(),
                    action: $action,
                    entityType: $entityType,
                    entityId: $command->entityId,
                    context: $context,
                    metadata: $metadata
                );

                try {
                    $this->writer->write($dto);
                } catch (Throwable $e) {
                    if ($this->fallbackLogger) {
                        $this->fallbackLogger->error('Behavior trace logging failed', [
                            'exception' => $e->getMessage(),
                            'action' => $action,
                        ]);
                    }
                }
            } catch (Throwable $e) {
            $this->reportFailure('Behavior trace logging failed', [
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
