<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

class AuditTrailAdminQueryInvalidArgumentException extends InvalidArgumentMaatifyException implements EventLoggingExceptionInterface
{
    public static function invalidId(string $field): self
    {
        return new self('Invalid AuditTrail Admin Query ID: ' . $field);
    }

    public static function invalidLength(string $field): self
    {
        return new self('Invalid AuditTrail Admin Query length: ' . $field);
    }

    public static function invalidEncoding(string $field): self
    {
        return new self('Invalid AuditTrail Admin Query UTF-8 encoding: ' . $field);
    }

    public static function invalidDateRange(): self
    {
        return new self('Invalid AuditTrail Admin Query date range: after must be before or equal to before');
    }
}
