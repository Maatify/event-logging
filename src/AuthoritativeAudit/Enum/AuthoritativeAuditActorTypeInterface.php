<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Enum;

interface AuthoritativeAuditActorTypeInterface
{
    public function value(): string;
}
