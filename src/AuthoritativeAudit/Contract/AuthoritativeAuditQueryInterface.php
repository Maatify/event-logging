<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Contract;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;

interface AuthoritativeAuditQueryInterface
{
    /**
     * @return array<AuthoritativeAuditViewDTO>
     * @throws AuthoritativeAuditStorageException
     */
    public function find(AuthoritativeAuditQueryDTO $query): array;
}
