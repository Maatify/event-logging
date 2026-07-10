<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Contract;

use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryPageDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;

interface AuditTrailPaginatedQueryInterface
{
    /**
     * @throws AuditTrailStorageException
     */
    public function findPage(AuditTrailQueryDTO $query): AuditTrailQueryPageDTO;
}
