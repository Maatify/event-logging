<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryExecutionException;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecuritySignalsAdminQueryExecutionExceptionTest extends TestCase
{
    public function testExecutionFactoryUsesExactMessageInheritanceAndPrevious(): void
    {
        $previous = new RuntimeException('bad descriptor');
        $exception = SecuritySignalsAdminQueryExecutionException::executionFailed($previous);

        $this->assertInstanceOf(SystemMaatifyException::class, $exception);
        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
        $this->assertSame('SecuritySignals Admin Query execution failed: bad descriptor', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
