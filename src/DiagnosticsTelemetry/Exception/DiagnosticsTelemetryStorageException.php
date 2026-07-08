<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;

class DiagnosticsTelemetryStorageException extends SystemMaatifyException
{
    public function defaultErrorCode(): ErrorCodeInterface
    {
        return ErrorCodeEnum::DATABASE_CONNECTION_FAILED;
    }
}
