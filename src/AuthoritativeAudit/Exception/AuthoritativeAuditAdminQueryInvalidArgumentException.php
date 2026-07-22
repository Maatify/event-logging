<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

final class AuthoritativeAuditAdminQueryInvalidArgumentException extends InvalidArgumentMaatifyException implements EventLoggingExceptionInterface
{
    public static function invalidId(string $field): self
    {
        return new self("Invalid AuthoritativeAudit Admin Query ID: {$field}");
    }

    public static function invalidLength(string $field): self
    {
        return new self("Invalid AuthoritativeAudit Admin Query length: {$field}");
    }

    public static function invalidEncoding(string $field): self
    {
        return new self("Invalid AuthoritativeAudit Admin Query UTF-8 encoding: {$field}");
    }

    public static function invalidDateRange(): self
    {
        return new self("Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before");
    }
}
