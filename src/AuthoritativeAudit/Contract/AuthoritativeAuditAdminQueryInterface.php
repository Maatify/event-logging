<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Contract;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;

interface AuthoritativeAuditAdminQueryInterface
{
    /**
     * @throws AuthoritativeAuditStorageException
     * @throws AuthoritativeAuditAdminQueryExecutionException
     * @throws AuthoritativeAuditAdminQueryInvalidArgumentException
     */
    public function paginate(AuthoritativeAuditAdminQueryRequestDTO $request): AuthoritativeAuditAdminPageResultDTO;
}
