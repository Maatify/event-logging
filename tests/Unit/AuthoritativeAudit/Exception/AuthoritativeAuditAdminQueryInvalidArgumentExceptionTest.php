<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Exception;

use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException
 */
final class AuthoritativeAuditAdminQueryInvalidArgumentExceptionTest extends TestCase
{
    public function testItExtendsCorrectClassesAndInterfaces(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('test');

        $this->assertInstanceOf(InvalidArgumentMaatifyException::class, $exception);
        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
    }

    public function testInvalidIdMessage(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('actorId');

        $this->assertSame('Invalid AuthoritativeAudit Admin Query ID: actorId', $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }

    public function testInvalidLengthMessage(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength('action');

        $this->assertSame('Invalid AuthoritativeAudit Admin Query length: action', $exception->getMessage());
    }

    public function testInvalidEncodingMessage(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding('targetType');

        $this->assertSame('Invalid AuthoritativeAudit Admin Query UTF-8 encoding: targetType', $exception->getMessage());
    }

    public function testInvalidDateRangeMessage(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidDateRange();

        $this->assertSame('Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before', $exception->getMessage());
    }
}
