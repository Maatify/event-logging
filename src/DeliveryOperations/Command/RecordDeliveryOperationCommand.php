<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Command;

use DateTimeImmutable;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryActorTypeInterface;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryChannelEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryOperationTypeEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryStatusEnum;
use InvalidArgumentException;

final readonly class RecordDeliveryOperationCommand
{
    /**
     * @param array<mixed>|null $metadata
     */
    public function __construct(
        public DeliveryChannelEnum|string $channel,
        public DeliveryOperationTypeEnum|string $operationType,
        public DeliveryStatusEnum|string $status,
        public int $attemptNo = 0,
        public DeliveryActorTypeInterface|string|null $actorType = null,
        public ?int $actorId = null,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?DateTimeImmutable $scheduledAt = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?string $correlationId = null,
        public ?string $requestId = null,
        public ?string $provider = null,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?array $metadata = null
    ) {
        if (is_string($this->channel) && trim($this->channel) === '') {
            throw new InvalidArgumentException('Delivery operation channel must not be empty.');
        }
        if (is_string($this->operationType) && trim($this->operationType) === '') {
            throw new InvalidArgumentException('Delivery operation type must not be empty.');
        }
        if (is_string($this->status) && trim($this->status) === '') {
            throw new InvalidArgumentException('Delivery operation status must not be empty.');
        }
        if ($this->attemptNo < 0) {
            throw new InvalidArgumentException('Delivery operation attempt number must be zero or greater.');
        }
        foreach (['actorId' => $this->actorId, 'targetId' => $this->targetId] as $field => $value) {
            if ($value !== null && $value < 1) {
                throw new InvalidArgumentException(sprintf('Delivery operation %s must be a positive integer when provided.', $field));
            }
        }
    }
}
