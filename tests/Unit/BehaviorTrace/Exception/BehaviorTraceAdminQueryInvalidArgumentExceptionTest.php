<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Exception;

use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryInvalidArgumentException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use PHPUnit\Framework\TestCase;

final class BehaviorTraceAdminQueryInvalidArgumentExceptionTest extends TestCase
{
    public function testNamedConstructorsHaveStableMessagesAndMarker(): void
    {
        $exception = BehaviorTraceAdminQueryInvalidArgumentException::invalidId('actorId');

        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
        $this->assertInstanceOf(InvalidArgumentMaatifyException::class, $exception);
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
        $this->assertSame('Invalid BehaviorTrace Admin Query ID: actorId', $exception->getMessage());
        $this->assertSame('Invalid BehaviorTrace Admin Query length: action', BehaviorTraceAdminQueryInvalidArgumentException::invalidLength('action')->getMessage());
        $this->assertSame('Invalid BehaviorTrace Admin Query UTF-8 encoding: actorType', BehaviorTraceAdminQueryInvalidArgumentException::invalidEncoding('actorType')->getMessage());
        $this->assertSame('Invalid BehaviorTrace Admin Query date range: after must be before or equal to before', BehaviorTraceAdminQueryInvalidArgumentException::invalidDateRange()->getMessage());
    }
}
