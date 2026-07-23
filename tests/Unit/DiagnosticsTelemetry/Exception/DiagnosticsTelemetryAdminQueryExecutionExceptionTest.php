<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Exception;

use Exception;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryExecutionException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryExecutionException
 */
final class DiagnosticsTelemetryAdminQueryExecutionExceptionTest extends TestCase
{
    public function testItExtendsCorrectClassesAndInterfaces(): void
    {
        $exception = DiagnosticsTelemetryAdminQueryExecutionException::executionFailed(new Exception('test'));

        $this->assertInstanceOf(SystemMaatifyException::class, $exception);
        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
    }

    public function testExecutionFailedReturnsConfiguredException(): void
    {
        $previous = new Exception('Inner failure message');
        $exception = DiagnosticsTelemetryAdminQueryExecutionException::executionFailed($previous);

        $this->assertSame('DiagnosticsTelemetry Admin Query execution failed: Inner failure message', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
    }
}
