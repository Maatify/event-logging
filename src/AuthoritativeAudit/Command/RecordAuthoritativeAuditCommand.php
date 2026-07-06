<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Command;

use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditActorTypeInterface;
use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditRiskLevelEnum;
use InvalidArgumentException;

final readonly class RecordAuthoritativeAuditCommand
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        public string $action,
        public string $targetType,
        public ?int $targetId,
        public AuthoritativeAuditRiskLevelEnum|string $riskLevel,
        public AuthoritativeAuditActorTypeInterface|string $actorType,
        public ?int $actorId,
        public array $payload,
        public string $correlationId
    ) {
        if (trim($this->action) === '') {
            throw new InvalidArgumentException('Authoritative audit action must not be empty.');
        }
        if (trim($this->targetType) === '') {
            throw new InvalidArgumentException('Authoritative audit target type must not be empty.');
        }
        if (trim($this->correlationId) === '') {
            throw new InvalidArgumentException('Authoritative audit correlation id must not be empty.');
        }
        if ($this->targetId !== null && $this->targetId < 1) {
            throw new InvalidArgumentException('Authoritative audit target id must be a positive integer when provided.');
        }
        if ($this->actorId !== null && $this->actorId < 1) {
            throw new InvalidArgumentException('Authoritative audit actor id must be a positive integer when provided.');
        }
    }
}
