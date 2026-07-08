<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;

class BehaviorTraceStorageException extends SystemMaatifyException
{
    public function defaultErrorCode(): ErrorCodeInterface
    {
        return ErrorCodeEnum::DATABASE_CONNECTION_FAILED;
    }
}
