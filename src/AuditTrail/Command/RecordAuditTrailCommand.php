<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Command;

use Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum;
use InvalidArgumentException;

final readonly class RecordAuditTrailCommand
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $eventKey,
        public string|AuditTrailActorTypeEnum $actorType,
        public ?int $actorId,
        public string $entityType,
        public ?int $entityId,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?array $metadata = null,
        public ?string $referrerRouteName = null,
        public ?string $referrerPath = null,
        public ?string $referrerHost = null,
        public ?string $correlationId = null,
        public ?string $requestId = null,
        public ?string $routeName = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {
        if (trim($this->eventKey) === '') {
            throw new InvalidArgumentException('Audit trail event key must not be empty.');
        }
        if (trim($this->entityType) === '') {
            throw new InvalidArgumentException('Audit trail entity type must not be empty.');
        }
        foreach (['actorId' => $this->actorId, 'entityId' => $this->entityId, 'subjectId' => $this->subjectId] as $field => $value) {
            if ($value !== null && $value < 1) {
                throw new InvalidArgumentException(sprintf('Audit trail %s must be a positive integer when provided.', $field));
            }
        }
    }
}
