<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

final class DiagnosticsTelemetryAdminQueryInvalidArgumentException extends InvalidArgumentMaatifyException implements EventLoggingExceptionInterface
{
    public static function invalidId(string $field): self
    {
        return new self("Invalid DiagnosticsTelemetry Admin Query ID: {$field}");
    }

    public static function invalidLength(string $field): self
    {
        return new self("Invalid DiagnosticsTelemetry Admin Query length: {$field}");
    }

    public static function invalidEncoding(string $field): self
    {
        return new self("Invalid DiagnosticsTelemetry Admin Query UTF-8 encoding: {$field}");
    }

    public static function invalidDateRange(): self
    {
        return new self("Invalid DiagnosticsTelemetry Admin Query date range: after must be before or equal to before");
    }
}
