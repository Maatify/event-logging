<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO
 */
final class DiagnosticsTelemetryAdminQueryRequestDTOTest extends TestCase
{
    public function testItInitializesEmpty(): void
    {
        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO();

        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->eventKey);
        $this->assertNull($dto->severity);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->after);
        $this->assertNull($dto->before);
        $this->assertNull($dto->page);
        $this->assertNull($dto->perPage);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);

        $expectedSerialization = [
            'actorType' => null,
            'actorId' => null,
            'eventKey' => null,
            'severity' => null,
            'requestId' => null,
            'correlationId' => null,
            'after' => null,
            'before' => null,
            'page' => null,
            'perPage' => null,
            'sortBy' => null,
            'sortDirection' => null,
        ];

        $this->assertSame($expectedSerialization, $dto->jsonSerialize());
    }

    public function testItNormalizesAndPreservesValidInputs(): void
    {
        $after = new DateTimeImmutable('2023-01-01T00:00:00Z');
        $before = new DateTimeImmutable('2023-01-02T00:00:00Z');

        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(
            actorType: ' admin ',
            actorId: 42,
            eventKey: ' system_start ',
            severity: ' WARNING ',
            requestId: ' req-123 ',
            correlationId: '  corr-99  ',
            after: $after,
            before: $before,
            page: '2',
            perPage: 50,
            sortBy: ' occurred_at ',
            sortDirection: ' desc '
        );

        $this->assertSame('admin', $dto->actorType);
        $this->assertSame(42, $dto->actorId);
        $this->assertSame('system_start', $dto->eventKey);
        $this->assertSame('WARNING', $dto->severity);
        $this->assertSame('req-123', $dto->requestId);
        $this->assertSame('corr-99', $dto->correlationId);
        $this->assertSame($after, $dto->after);
        $this->assertSame($before, $dto->before);
        $this->assertSame('2', $dto->page);
        $this->assertSame(50, $dto->perPage);
        $this->assertSame('occurred_at', $dto->sortBy);
        $this->assertSame('DESC', $dto->sortDirection);
    }

    public function testItNormalizesBlankStringsToNull(): void
    {
        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(
            actorType: '',
            eventKey: " \t ",
            severity: ' ',
            requestId: '',
            correlationId: '',
            sortBy: '   ',
            sortDirection: ''
        );

        $this->assertNull($dto->actorType);
        $this->assertNull($dto->eventKey);
        $this->assertNull($dto->severity);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);
    }

    public function testItNormalizesUnknownSortByToNull(): void
    {
        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(sortBy: 'invalid_column');
        $this->assertNull($dto->sortBy);
    }

    public function testItNormalizesUnknownSortDirectionToNull(): void
    {
        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(sortDirection: 'UP');
        $this->assertNull($dto->sortDirection);
    }

    public function testItAllowsIndependentActorIdAndTypeFilters(): void
    {
        // Valid to provide ID without Type
        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(actorId: 10);
        $this->assertSame(10, $dto->actorId);
        $this->assertNull($dto->actorType);
    }

    public function testItAllowsEqualDateBoundaries(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00Z');
        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(after: $date, before: $date);

        $this->assertSame($date, $dto->after);
        $this->assertSame($date, $dto->before);
    }

    public function testItRejectsAfterGreaterThanBefore(): void
    {
        $after = new DateTimeImmutable('2023-01-02T00:00:00Z');
        $before = new DateTimeImmutable('2023-01-01T00:00:00Z');

        $this->expectException(DiagnosticsTelemetryAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DiagnosticsTelemetry Admin Query date range: after must be before or equal to before');

        new DiagnosticsTelemetryAdminQueryRequestDTO(after: $after, before: $before);
    }

    public function testItRejectsZeroOrNegativeActorId(): void
    {
        $this->expectException(DiagnosticsTelemetryAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DiagnosticsTelemetry Admin Query ID: actorId');
        new DiagnosticsTelemetryAdminQueryRequestDTO(actorId: 0);
    }

    /**
     * @dataProvider lengthValidationDataProvider
     */
    public function testLengthValidation(string $field, int $maxLength, bool $shouldThrow): void
    {
        if ($shouldThrow) {
            $this->expectException(DiagnosticsTelemetryAdminQueryInvalidArgumentException::class);
            $this->expectExceptionMessage("Invalid DiagnosticsTelemetry Admin Query length: {$field}");
        }

        $value = str_repeat('a', $shouldThrow ? $maxLength + 1 : $maxLength);

        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(
            actorType: $field === 'actorType' ? $value : null,
            eventKey: $field === 'eventKey' ? $value : null,
            severity: $field === 'severity' ? $value : null,
            requestId: $field === 'requestId' ? $value : null,
            correlationId: $field === 'correlationId' ? $value : null,
            sortBy: $field === 'sortBy' ? ($shouldThrow ? $value : null) : null,
            sortDirection: $field === 'sortDirection' ? ($shouldThrow ? $value : null) : null
        );

        if (!$shouldThrow) {
            $this->assertSame($value, $dto->{$field});
        }
    }

    /**
     * @return array<int, array{string, int, bool}>
     */
    public static function lengthValidationDataProvider(): array
    {
        return [
            ['actorType', 64, false],
            ['actorType', 64, true],
            ['eventKey', 128, false],
            ['eventKey', 128, true],
            ['severity', 32, false],
            ['severity', 32, true],
            ['requestId', 36, false],
            ['requestId', 36, true],
            ['correlationId', 36, false],
            ['correlationId', 36, true],
            ['sortBy', 64, true],
            ['sortDirection', 4, true],
        ];
    }

    /**
     * @dataProvider invalidUtf8DataProvider
     */
    public function testItRejectsInvalidUtf8(string $field): void
    {
        $this->expectException(DiagnosticsTelemetryAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid DiagnosticsTelemetry Admin Query UTF-8 encoding: {$field}");

        $invalidUtf8 = "\x80\x81";
        new DiagnosticsTelemetryAdminQueryRequestDTO(
            eventKey: $field === 'eventKey' ? $invalidUtf8 : null,
            sortBy: $field === 'sortBy' ? $invalidUtf8 : null,
            sortDirection: $field === 'sortDirection' ? $invalidUtf8 : null
        );
    }

    /**
     * @return array<int, array{string}>
     */
    public static function invalidUtf8DataProvider(): array
    {
        return [
            ['eventKey'],
            ['sortBy'],
            ['sortDirection'],
        ];
    }

    public function testItPreservesPageAndPerPageTypes(): void
    {
        $dto1 = new DiagnosticsTelemetryAdminQueryRequestDTO(page: 1, perPage: 20);
        $this->assertSame(1, $dto1->page);
        $this->assertSame(20, $dto1->perPage);

        $dto2 = new DiagnosticsTelemetryAdminQueryRequestDTO(page: '2', perPage: '50');
        $this->assertSame('2', $dto2->page);
        $this->assertSame('50', $dto2->perPage);

        $dto3 = new DiagnosticsTelemetryAdminQueryRequestDTO(page: null, perPage: null);
        $this->assertNull($dto3->page);
        $this->assertNull($dto3->perPage);
    }

    public function testJsonSerializeHasExactKeysAndOrderingAndValues(): void
    {
        $after = new DateTimeImmutable('2023-01-01T10:00:00Z');
        $before = new DateTimeImmutable('2023-01-02T10:00:00Z', new DateTimeZone('Europe/Berlin'));

        $dto = new DiagnosticsTelemetryAdminQueryRequestDTO(
            actorType: 'sys',
            actorId: 1,
            eventKey: 'login',
            severity: 'INFO',
            requestId: 'req-1',
            correlationId: 'corr-1',
            after: $after,
            before: $before,
            page: 1,
            perPage: 25,
            sortBy: 'occurred_at',
            sortDirection: 'ASC'
        );

        $serialized = $dto->jsonSerialize();

        $expectedKeys = [
            'actorType',
            'actorId',
            'eventKey',
            'severity',
            'requestId',
            'correlationId',
            'after',
            'before',
            'page',
            'perPage',
            'sortBy',
            'sortDirection',
        ];

        $this->assertSame($expectedKeys, array_keys($serialized));

        $this->assertSame('sys', $serialized['actorType']);
        $this->assertSame(1, $serialized['actorId']);
        $this->assertSame('login', $serialized['eventKey']);
        $this->assertSame('INFO', $serialized['severity']);
        $this->assertSame('req-1', $serialized['requestId']);
        $this->assertSame('corr-1', $serialized['correlationId']);
        $this->assertSame($after->format(\DATE_ATOM), $serialized['after']);
        $this->assertSame($before->format(\DATE_ATOM), $serialized['before']);
        $this->assertSame(1, $serialized['page']);
        $this->assertSame(25, $serialized['perPage']);
        $this->assertSame('occurred_at', $serialized['sortBy']);
        $this->assertSame('ASC', $serialized['sortDirection']);
    }
}
