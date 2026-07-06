<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Contract;

use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;

interface BehaviorTracePolicyInterface
{
    public function normalizeActorType(string|BehaviorTraceActorTypeInterface $actorType): BehaviorTraceActorTypeInterface;

    public function validateMetadataSize(string $json): bool;
}
