<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Contract;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryPageDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;

interface AuthoritativeAuditPaginatedQueryInterface
{
    /**
     * @throws AuthoritativeAuditStorageException
     */
    public function findPage(AuthoritativeAuditQueryDTO $query): AuthoritativeAuditQueryPageDTO;
}
