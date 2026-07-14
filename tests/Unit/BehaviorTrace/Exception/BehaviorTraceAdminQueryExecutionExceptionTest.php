<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Exception;

use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryExecutionException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BehaviorTraceAdminQueryExecutionExceptionTest extends TestCase
{
    public function testExecutionFailedPreservesPreviousAndMaatifyErrorCode(): void
    {
        $previous = new RuntimeException('boom');
        $exception = BehaviorTraceAdminQueryExecutionException::executionFailed($previous);

        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
        $this->assertInstanceOf(SystemMaatifyException::class, $exception);
        $this->assertSame(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
        $this->assertSame('BehaviorTrace Admin Query execution failed: boom', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
