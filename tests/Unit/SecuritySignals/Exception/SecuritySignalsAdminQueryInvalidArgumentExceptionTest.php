<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Exception;

use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryInvalidArgumentException;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use PHPUnit\Framework\TestCase;

final class SecuritySignalsAdminQueryInvalidArgumentExceptionTest extends TestCase
{
    public function testFactoriesUseExactMessagesAndInheritance(): void
    {
        $exception = SecuritySignalsAdminQueryInvalidArgumentException::invalidId('actorId');

        $this->assertInstanceOf(InvalidArgumentMaatifyException::class, $exception);
        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
        $this->assertSame('Invalid SecuritySignals Admin Query ID: actorId', $exception->getMessage());
        $this->assertSame('Invalid SecuritySignals Admin Query length: actorType', SecuritySignalsAdminQueryInvalidArgumentException::invalidLength('actorType')->getMessage());
        $this->assertSame('Invalid SecuritySignals Admin Query UTF-8 encoding: actorType', SecuritySignalsAdminQueryInvalidArgumentException::invalidEncoding('actorType')->getMessage());
        $this->assertSame(
            'Invalid SecuritySignals Admin Query date range: after must be before or equal to before',
            SecuritySignalsAdminQueryInvalidArgumentException::invalidDateRange()->getMessage(),
        );
    }
}
