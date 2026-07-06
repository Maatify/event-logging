<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Contract;

use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryActorTypeInterface;

interface DeliveryOperationsPolicyInterface
{
    public function normalizeActorType(DeliveryActorTypeInterface|string $actorType): string;
    public function validateMetadataSize(string $json): bool;
}
