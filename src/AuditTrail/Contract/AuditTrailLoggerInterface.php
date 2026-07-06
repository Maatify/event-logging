<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Contract;

use Maatify\EventLogging\AuditTrail\DTO\AuditTrailRecordDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;

interface AuditTrailLoggerInterface
{
    /**
     * Persist an audit trail record.
     *
     * @throws AuditTrailStorageException
     */
    public function write(AuditTrailRecordDTO $record): void;
}
