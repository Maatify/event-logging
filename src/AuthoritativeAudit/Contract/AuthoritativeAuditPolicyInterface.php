<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Contract;

use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditActorTypeInterface;

interface AuthoritativeAuditPolicyInterface
{
    public function normalizeActorType(AuthoritativeAuditActorTypeInterface|string $actorType): string;

    /**
     * @param array<mixed> $payload
     */
    public function validatePayload(array $payload): bool;
}
