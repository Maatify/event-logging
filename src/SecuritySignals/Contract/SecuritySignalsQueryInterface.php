<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Contract;

use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;

interface SecuritySignalsQueryInterface
{
    /**
     * @return array<SecuritySignalsViewDTO>
     * @throws SecuritySignalsStorageException
     */
    public function find(SecuritySignalsQueryDTO $query): array;
}
