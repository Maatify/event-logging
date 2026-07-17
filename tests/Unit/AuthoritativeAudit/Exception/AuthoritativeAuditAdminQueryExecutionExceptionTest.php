<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Exception;

use Exception;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminQueryExecutionExceptionTest extends TestCase
{
    public function testExecutionFailed(): void
    {
        $previous = new Exception('Some error');
        $exception = AuthoritativeAuditAdminQueryExecutionException::executionFailed($previous);

        $this->assertSame('AuthoritativeAudit Admin Query execution failed: Some error', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
    }
}
