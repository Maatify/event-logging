<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Exception;

use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminQueryInvalidArgumentExceptionTest extends TestCase
{
    public function testInvalidId(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('actorId');
        $this->assertSame('Invalid AuthoritativeAudit Admin Query ID: actorId', $exception->getMessage());
    }

    public function testInvalidLength(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength('eventId');
        $this->assertSame('Invalid AuthoritativeAudit Admin Query length: eventId', $exception->getMessage());
    }

    public function testInvalidEncoding(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding('action');
        $this->assertSame('Invalid AuthoritativeAudit Admin Query UTF-8 encoding: action', $exception->getMessage());
    }

    public function testInvalidDateRange(): void
    {
        $exception = AuthoritativeAuditAdminQueryInvalidArgumentException::invalidDateRange();
        $this->assertSame('Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before', $exception->getMessage());
    }
}
