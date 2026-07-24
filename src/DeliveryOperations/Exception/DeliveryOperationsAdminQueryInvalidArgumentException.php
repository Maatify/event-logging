<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

final class DeliveryOperationsAdminQueryInvalidArgumentException extends InvalidArgumentMaatifyException implements EventLoggingExceptionInterface
{
    public static function invalidId(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query ID: {$field}");
    }
    public static function invalidRetryRange(): self
    {
        return new self("Invalid DeliveryOperations Admin Query retry range: attempt_no_min must be less than or equal to attempt_no_max");
    }
    public static function invalidRetryValue(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query retry value: {$field}");
    }
    public static function invalidLength(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query length: {$field}");
    }
    public static function invalidEncoding(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query UTF-8 encoding: {$field}");
    }
    public static function invalidDateRange(string $rangeName): self
    {
        return new self("Invalid DeliveryOperations Admin Query date range: {$rangeName} after must be before or equal to before");
    }
    public static function invalidNullState(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query null-state input: {$field}");
    }
    public static function invalidMetadataCount(): self
    {
        return new self("Invalid DeliveryOperations Admin Query metadata filter count");
    }
    public static function invalidMetadataPath(): self
    {
        return new self("Invalid DeliveryOperations Admin Query metadata path or shape");
    }
    public static function invalidMetadataValue(): self
    {
        return new self("Invalid DeliveryOperations Admin Query metadata value type");
    }
}
