<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Contract;

use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminPageResultDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryExecutionException;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryInvalidArgumentException;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;

interface SecuritySignalsAdminQueryInterface
{
    /**
     * @throws SecuritySignalsAdminQueryInvalidArgumentException
     * @throws SecuritySignalsAdminQueryExecutionException
     * @throws SecuritySignalsStorageException
     */
    public function paginate(
        SecuritySignalsAdminQueryRequestDTO $request,
    ): SecuritySignalsAdminPageResultDTO;
}
