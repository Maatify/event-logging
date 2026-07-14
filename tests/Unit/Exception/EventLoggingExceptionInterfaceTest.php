<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\Exception;

use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventLoggingExceptionInterfaceTest extends TestCase
{
    /**
     * @param class-string<SystemMaatifyException> $exceptionClass
     */
    #[DataProvider('storageExceptionProvider')]
    public function testStorageExceptionsImplementPackageMarker(string $exceptionClass): void
    {
        $message = 'storage failure';
        $previous = new \RuntimeException('previous failure');

        $exception = new $exceptionClass(
            message: $message,
            previous: $previous,
        );

        self::assertInstanceOf(
            EventLoggingExceptionInterface::class,
            $exception,
        );

        self::assertInstanceOf(
            SystemMaatifyException::class,
            $exception,
        );

        self::assertSame(
            ErrorCodeEnum::DATABASE_CONNECTION_FAILED,
            $exception->getErrorCode(),
        );

        self::assertSame($message, $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @return array<string, array{class-string<SystemMaatifyException>}>
     */
    public static function storageExceptionProvider(): array
    {
        return [
            'AuditTrailStorageException' => [AuditTrailStorageException::class],
            'AuthoritativeAuditStorageException' => [AuthoritativeAuditStorageException::class],
            'BehaviorTraceStorageException' => [BehaviorTraceStorageException::class],
            'DeliveryOperationsStorageException' => [DeliveryOperationsStorageException::class],
            'DiagnosticsTelemetryStorageException' => [DiagnosticsTelemetryStorageException::class],
            'SecuritySignalsStorageException' => [SecuritySignalsStorageException::class],
        ];
    }
}
