<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Exception;

use Exception;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryExecutionException;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use PHPUnit\Framework\TestCase;

final class DeliveryOperationsAdminQueryExecutionExceptionTest extends TestCase
{
    public function testExecutionFailedFactory(): void
    {
        $previous = new Exception('Original message');
        $exception = DeliveryOperationsAdminQueryExecutionException::executionFailed($previous);

        $this->assertEquals('DeliveryOperations Admin Query execution failed: Original message', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
    }
}
