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

    public function testItRejectsExcessiveLengthString(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: actorType');

        $longString = str_repeat('a', 33); // max 32
        new AuthoritativeAuditAdminQueryRequestDTO(actorType: $longString);
    }

    public function testItRejectsInvalidUtf8(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query UTF-8 encoding: action');

        $invalidUtf8 = "\x80\x81";
        new AuthoritativeAuditAdminQueryRequestDTO(action: $invalidUtf8);
    }

    public function testJsonSerializeHasExactKeysAndOrdering(): void
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
        $this->assertSame($after->format(\DATE_ATOM), $serialized['after']);
        $this->assertSame($before->format(\DATE_ATOM), $serialized['before']);
    }
}
