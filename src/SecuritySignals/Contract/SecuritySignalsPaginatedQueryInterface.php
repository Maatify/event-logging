<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Contract;

use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryPageDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;

interface SecuritySignalsPaginatedQueryInterface
{
    /**
     * @throws SecuritySignalsStorageException
     */
    public function findPage(SecuritySignalsQueryDTO $query): SecuritySignalsQueryPageDTO;
}
