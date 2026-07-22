<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Exception;

use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryInvalidArgumentException;
use Maatify\EventLogging\Exception\EventLoggingExceptionInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryInvalidArgumentException
 */
final class DiagnosticsTelemetryAdminQueryInvalidArgumentExceptionTest extends TestCase
{
    public function testItExtendsCorrectClassesAndInterfaces(): void
    {
        $exception = DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidId('test');

        $this->assertInstanceOf(InvalidArgumentMaatifyException::class, $exception);
        $this->assertInstanceOf(EventLoggingExceptionInterface::class, $exception);
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testInvalidIdMessage(string $field): void
    {
        $exception = DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidId($field);

        $this->assertSame("Invalid DiagnosticsTelemetry Admin Query ID: {$field}", $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testInvalidLengthMessage(string $field): void
    {
        $exception = DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidLength($field);

        $this->assertSame("Invalid DiagnosticsTelemetry Admin Query length: {$field}", $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testInvalidEncodingMessage(string $field): void
    {
        $exception = DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidEncoding($field);

        $this->assertSame("Invalid DiagnosticsTelemetry Admin Query UTF-8 encoding: {$field}", $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }

    /**
     * @return array<int, array{string}>
     */
    public static function fieldProvider(): array
    {
        return [
            ['actorType'],
            ['actorId'],
            ['eventKey'],
            ['severity'],
            ['requestId'],
            ['correlationId'],
            ['sortBy'],
            ['sortDirection'],
        ];
    }

    public function testInvalidDateRangeMessage(): void
    {
        $exception = DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidDateRange();

        $this->assertSame('Invalid DiagnosticsTelemetry Admin Query date range: after must be before or equal to before', $exception->getMessage());
        $this->assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }
}
