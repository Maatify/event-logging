<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Exception;

use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryExecutionException;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryInvalidArgumentException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditTrailAdminQueryExceptionTest extends TestCase
{
    public function testInvalidArgumentNamedConstructorsHaveStableMessagesAndMarker(): void
    {
        $exception = AuditTrailAdminQueryInvalidArgumentException::invalidId('actorId');
        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
        $this->assertInstanceOf(InvalidArgumentMaatifyException::class, $exception);
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
        $this->assertSame('Invalid AuditTrail Admin Query ID: actorId', $exception->getMessage());

        $this->assertSame(
            'Invalid AuditTrail Admin Query length: eventKey',
            AuditTrailAdminQueryInvalidArgumentException::invalidLength('eventKey')->getMessage()
        );
        $this->assertSame(
            'Invalid AuditTrail Admin Query UTF-8 encoding: actorType',
            AuditTrailAdminQueryInvalidArgumentException::invalidEncoding('actorType')->getMessage()
        );
        $this->assertSame(
            'Invalid AuditTrail Admin Query date range: after must be before or equal to before',
            AuditTrailAdminQueryInvalidArgumentException::invalidDateRange()->getMessage()
        );
    }

    public function testExecutionFailedPreservesPreviousAndMaatifyErrorCode(): void
    {
        $previous = new RuntimeException('boom');
        $exception = AuditTrailAdminQueryExecutionException::executionFailed($previous);

        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
        $this->assertInstanceOf(SystemMaatifyException::class, $exception);
        $this->assertSame(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
        $this->assertSame('AuditTrail Admin Query execution failed: boom', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
