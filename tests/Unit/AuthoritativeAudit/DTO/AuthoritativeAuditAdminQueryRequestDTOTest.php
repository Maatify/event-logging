<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO
 */
final class AuthoritativeAuditAdminQueryRequestDTOTest extends TestCase
{
    public function testItConstructsWithAllDefaultsAndSerializesCorrectly(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO();

        $this->assertNull($dto->eventId);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertNull($dto->action);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->after);
        $this->assertNull($dto->before);
        $this->assertNull($dto->page);
        $this->assertNull($dto->perPage);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);

        $expectedSerialization = [
            'eventId' => null,
            'actorType' => null,
            'actorId' => null,
            'targetType' => null,
            'targetId' => null,
            'action' => null,
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

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: '  uuid-1234  ',
            actorType: ' admin ',
            actorId: 42,
            targetType: ' user ',
            targetId: 10,
            action: ' update ',
            correlationId: '  corr-99  ',
            after: $after,
            before: $before,
            page: '2',
            perPage: 50,
            sortBy: ' occurred_at ',
            sortDirection: ' desc '
        );

        $this->assertSame('uuid-1234', $dto->eventId);
        $this->assertSame('admin', $dto->actorType);
        $this->assertSame(42, $dto->actorId);
        $this->assertSame('user', $dto->targetType);
        $this->assertSame(10, $dto->targetId);
        $this->assertSame('update', $dto->action);
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
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: '   ',
            actorType: '',
            targetType: " \t ",
            action: ' ',
            correlationId: '',
            sortBy: '   ',
            sortDirection: ''
        );

        $this->assertNull($dto->eventId);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->action);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);
    }

    public function testItNormalizesUnknownSortByToNull(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'invalid_column');
        $this->assertNull($dto->sortBy);
    }

    public function testItNormalizesUnknownSortDirectionToNull(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(sortDirection: 'UP');
        $this->assertNull($dto->sortDirection);
    }

    public function testItAllowsIndependentActorAndTargetFilters(): void
    {
        // Valid to provide ID without Type
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(actorId: 10, targetId: 20);
        $this->assertSame(10, $dto->actorId);
        $this->assertNull($dto->actorType);
        $this->assertSame(20, $dto->targetId);
        $this->assertNull($dto->targetType);
    }

    public function testItAllowsEqualDateBoundaries(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00Z');
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(after: $date, before: $date);

        $this->assertSame($date, $dto->after);
        $this->assertSame($date, $dto->before);
    }

    public function testItRejectsAfterGreaterThanBefore(): void
    {
        $after = new DateTimeImmutable('2023-01-02T00:00:00Z');
        $before = new DateTimeImmutable('2023-01-01T00:00:00Z');

        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before');

        new AuthoritativeAuditAdminQueryRequestDTO(after: $after, before: $before);
    }

    public function testItRejectsZeroOrNegativeActorId(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query ID: actorId');
        new AuthoritativeAuditAdminQueryRequestDTO(actorId: 0);
    }

    public function testItRejectsZeroOrNegativeTargetId(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query ID: targetId');
        new AuthoritativeAuditAdminQueryRequestDTO(targetId: -5);
    }

    /**
     * @dataProvider lengthValidationDataProvider
     */
    public function testLengthValidation(string $field, int $maxLength, bool $shouldThrow): void
    {
        if ($shouldThrow) {
            $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
            $this->expectExceptionMessage("Invalid AuthoritativeAudit Admin Query length: {$field}");
        }

        $value = str_repeat('a', $shouldThrow ? $maxLength + 1 : $maxLength);

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: $field === 'eventId' ? $value : null,
            actorType: $field === 'actorType' ? $value : null,
            targetType: $field === 'targetType' ? $value : null,
            action: $field === 'action' ? $value : null,
            correlationId: $field === 'correlationId' ? $value : null,
            sortBy: $field === 'sortBy' ? ($shouldThrow ? $value : null) : null, // sortBy max is 64 but valid value is only occurred_at
            sortDirection: $field === 'sortDirection' ? ($shouldThrow ? $value : null) : null // sortDirection max is 4
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
            ['eventId', 36, false],
            ['eventId', 36, true],
            ['actorType', 32, false],
            ['actorType', 32, true],
            ['targetType', 64, false],
            ['targetType', 64, true],
            ['action', 128, false],
            ['action', 128, true],
            ['correlationId', 36, false],
            ['correlationId', 36, true],
            ['sortBy', 64, true], // valid value occurred_at is less than 64, so we just test the throw case here with length 65
            ['sortDirection', 4, true], // valid ASC/DESC is less than 4, testing length 5 throw case
        ];
    }

    /**
     * @dataProvider invalidUtf8DataProvider
     */
    public function testItRejectsInvalidUtf8(string $field): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid AuthoritativeAudit Admin Query UTF-8 encoding: {$field}");

        $invalidUtf8 = "\x80\x81";
        new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: $field === 'eventId' ? $invalidUtf8 : null,
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
            ['eventId'],
            ['sortBy'],
            ['sortDirection'],
        ];
    }

    public function testItPreservesPageAndPerPageTypes(): void
    {
        $dto1 = new AuthoritativeAuditAdminQueryRequestDTO(page: 1, perPage: 20);
        $this->assertSame(1, $dto1->page);
        $this->assertSame(20, $dto1->perPage);

        $dto2 = new AuthoritativeAuditAdminQueryRequestDTO(page: '2', perPage: '50');
        $this->assertSame('2', $dto2->page);
        $this->assertSame('50', $dto2->perPage);

        $dto3 = new AuthoritativeAuditAdminQueryRequestDTO(page: null, perPage: null);
        $this->assertNull($dto3->page);
        $this->assertNull($dto3->perPage);
    }

    public function testJsonSerializeHasExactKeysAndOrderingAndValues(): void
    {
        $after = new DateTimeImmutable('2023-01-01T10:00:00Z');
        $before = new DateTimeImmutable('2023-01-02T10:00:00Z', new DateTimeZone('Europe/Berlin'));

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: 'event-1',
            actorType: 'sys',
            actorId: 1,
            targetType: 'file',
            targetId: 2,
            action: 'delete',
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
            'eventId',
            'actorType',
            'actorId',
            'targetType',
            'targetId',
            'action',
            'correlationId',
            'after',
            'before',
            'page',
            'perPage',
            'sortBy',
            'sortDirection',
        ];

        $this->assertSame($expectedKeys, array_keys($serialized));

        $this->assertSame('event-1', $serialized['eventId']);
        $this->assertSame('sys', $serialized['actorType']);
        $this->assertSame(1, $serialized['actorId']);
        $this->assertSame('file', $serialized['targetType']);
        $this->assertSame(2, $serialized['targetId']);
        $this->assertSame('delete', $serialized['action']);
        $this->assertSame('corr-1', $serialized['correlationId']);
        $this->assertSame($after->format(\DATE_ATOM), $serialized['after']);
        $this->assertSame($before->format(\DATE_ATOM), $serialized['before']);
        $this->assertSame(1, $serialized['page']);
        $this->assertSame(25, $serialized['perPage']);
        $this->assertSame('occurred_at', $serialized['sortBy']);
        $this->assertSame('ASC', $serialized['sortDirection']);
    }
}
