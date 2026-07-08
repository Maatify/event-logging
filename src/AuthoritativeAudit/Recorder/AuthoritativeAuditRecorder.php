<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Recorder;

use BackedEnum;
use Maatify\EventLogging\AuthoritativeAudit\Command\RecordAuthoritativeAuditCommand;
use Maatify\SharedCommon\Contracts\ClockInterface;
use UnitEnum;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditOutboxWriterInterface;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditPolicyInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;
use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditActorTypeInterface;
use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditRiskLevelEnum;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Ramsey\Uuid\Uuid;
use InvalidArgumentException;

class AuthoritativeAuditRecorder
{
    private readonly AuthoritativeAuditPolicyInterface $policy;

    public function __construct(
        private readonly AuthoritativeAuditOutboxWriterInterface $writer,
        private readonly ClockInterface $clock,
        ?AuthoritativeAuditPolicyInterface $policy = null
    ) {
        $this->policy = $policy ?? new AuthoritativeAuditDefaultPolicy();
    }

    /**
     * @param array<mixed> $payload
     * @throws AuthoritativeAuditStorageException
     * @throws InvalidArgumentException
     */
    public function record(
        string $action,
        string $targetType,
        ?int $targetId,
        AuthoritativeAuditRiskLevelEnum|string $riskLevel,
        AuthoritativeAuditActorTypeInterface|string $actorType,
        ?int $actorId,
        array $payload,
        string $correlationId
    ): void {
        $this->recordCommand(new RecordAuthoritativeAuditCommand(
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            riskLevel: $riskLevel,
            actorType: $actorType,
            actorId: $actorId,
            payload: $payload,
            correlationId: $correlationId
        ));
    }

    /**
     * @throws AuthoritativeAuditStorageException
     * @throws InvalidArgumentException
     */
    public function recordCommand(RecordAuthoritativeAuditCommand $command): void
    {
        if (!$this->policy->validatePayload($command->payload)) {
            throw new InvalidArgumentException('AuthoritativeAudit payload validation failed: Secrets detected or invalid content.');
        }

        $riskLevelStr = $this->enumToString($command->riskLevel);
        $normalizedActorType = $this->policy->normalizeActorType($command->actorType);

        $dto = new AuthoritativeAuditOutboxWriteDTO(
            eventId: Uuid::uuid4()->toString(),
            actorType: $normalizedActorType,
            actorId: $command->actorId,
            action: $this->truncateString($command->action, 128),
            targetType: $this->truncateString($command->targetType, 64),
            targetId: $command->targetId,
            riskLevel: $riskLevelStr,
            payload: $command->payload,
            correlationId: $this->truncateString($command->correlationId, 36),
            createdAt: $this->clock->now()
        );

        $this->writer->write($dto);
    }

    private function enumToString(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }
        if (is_object($value) && method_exists($value, 'value')) {
            /** @var mixed $val */
            $val = $value->value();
            if (is_string($val) || is_int($val)) {
                return (string) $val;
            }
        }

        if (is_string($value) || is_int($value)) {
            return (string) $value;
        }

        return '';
    }

    private function truncateString(string $value, int $limit): string
    {
        if (mb_strlen($value) > $limit) {
            return mb_substr($value, 0, $limit);
        }
        return $value;
    }
}
