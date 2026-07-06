<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Contract;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;

interface AuthoritativeAuditOutboxWriterInterface
{
    public function write(AuthoritativeAuditOutboxWriteDTO $dto): void;
}
