<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;

class SecuritySignalsStorageException extends SystemMaatifyException implements EventLoggingExceptionInterface
{
    public function defaultErrorCode(): ErrorCodeInterface
    {
        return ErrorCodeEnum::DATABASE_CONNECTION_FAILED;
    }
}
