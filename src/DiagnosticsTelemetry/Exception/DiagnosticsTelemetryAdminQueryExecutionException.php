<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Throwable;

final class DiagnosticsTelemetryAdminQueryExecutionException extends SystemMaatifyException implements EventLoggingExceptionInterface
{
    public static function executionFailed(Throwable $previous): self
    {
        return new self(
            message: 'DiagnosticsTelemetry Admin Query execution failed: ' . $previous->getMessage(),
            previous: $previous,
        );
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return ErrorCodeEnum::MAATIFY_ERROR;
    }
}
