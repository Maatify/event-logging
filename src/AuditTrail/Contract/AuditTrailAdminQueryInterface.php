<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Contract;

use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminPageResultDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryExecutionException;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryInvalidArgumentException;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;

interface AuditTrailAdminQueryInterface
{
    /**
     * @throws AuditTrailAdminQueryInvalidArgumentException
     * @throws AuditTrailAdminQueryExecutionException
     * @throws AuditTrailStorageException
     */
    public function paginate(AuditTrailAdminQueryRequestDTO $request): AuditTrailAdminPageResultDTO;
}
