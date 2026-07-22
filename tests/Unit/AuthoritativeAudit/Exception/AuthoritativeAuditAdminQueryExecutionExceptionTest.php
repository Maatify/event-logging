<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Exception;

use Exception;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException
 */
final class AuthoritativeAuditAdminQueryExecutionExceptionTest extends TestCase
{
    public function testItExtendsCorrectClassesAndInterfaces(): void
    {
        $exception = AuthoritativeAuditAdminQueryExecutionException::executionFailed(new Exception('test'));

        $this->assertInstanceOf(SystemMaatifyException::class, $exception);
        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
    }

    public function testExecutionFailedReturnsConfiguredException(): void
    {
        $previous = new Exception('Inner failure message');
        $exception = AuthoritativeAuditAdminQueryExecutionException::executionFailed($previous);

        $this->assertSame('AuthoritativeAudit Admin Query execution failed: Inner failure message', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
    }
}
