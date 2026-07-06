<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Contract;

use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;

interface AuditTrailQueryInterface
{
    /**
     * Query audit trail records.
     *
     * @return array<AuditTrailViewDTO>
     * @throws AuditTrailStorageException
     */
    public function find(AuditTrailQueryDTO $query): array;
}
