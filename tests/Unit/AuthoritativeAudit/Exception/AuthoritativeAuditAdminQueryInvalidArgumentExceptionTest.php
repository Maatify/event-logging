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

    /**
     * @dataProvider fieldProvider
     */
    public function testInvalidIdMessage(string $field): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId($field);

        $this->assertSame("Invalid AuthoritativeAudit Admin Query ID: {$field}", $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testInvalidLengthMessage(string $field): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength($field);

        $this->assertSame("Invalid AuthoritativeAudit Admin Query length: {$field}", $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testInvalidEncodingMessage(string $field): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding($field);

        $this->assertSame("Invalid AuthoritativeAudit Admin Query UTF-8 encoding: {$field}", $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }

    /**
     * @return array<int, array{string}>
     */
    public static function fieldProvider(): array
    {
        return [
            ['actorId'],
            ['targetId'],
            ['eventId'],
            ['actorType'],
            ['targetType'],
            ['action'],
            ['correlationId'],
            ['sortBy'],
            ['sortDirection'],
        ];
    }

    public function testInvalidDateRangeMessage(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidDateRange();

        $this->assertSame('Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before', $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }
}
