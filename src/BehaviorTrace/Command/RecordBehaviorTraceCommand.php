<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Command;

use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;
use InvalidArgumentException;

final readonly class RecordBehaviorTraceCommand
{
    /**
     * @param array<mixed>|null $metadata
     */
    public function __construct(
        public string $action,
        public BehaviorTraceActorTypeInterface|string $actorType,
        public ?int $actorId = null,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?string $correlationId = null,
        public ?string $requestId = null,
        public ?string $routeName = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?array $metadata = null
    ) {
        if (trim($this->action) === '') {
            throw new InvalidArgumentException('Behavior trace action must not be empty.');
        }
        foreach (['actorId' => $this->actorId, 'entityId' => $this->entityId] as $field => $value) {
            if ($value !== null && $value < 1) {
                throw new InvalidArgumentException(sprintf('Behavior trace %s must be a positive integer when provided.', $field));
            }
        }
    }
}
