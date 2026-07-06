<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Contract;

use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;

interface SecuritySignalsLoggerInterface
{
    /**
     * Persist a security signal record.
     *
     * @throws SecuritySignalsStorageException
     */
    public function write(SecuritySignalRecordDTO $record): void;
}
