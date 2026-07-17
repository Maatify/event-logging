<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminQueryRequestDTOTest extends TestCase
{
    public function testConstructValidValues(): void
    {
        $after = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $before = new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC'));

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: 'event-123',
            actorType: 'user',
            actorId: 42,
            targetType: 'resource',
            targetId: 100,
            action: 'create',
            correlationId: 'corr-456',
            after: $after,
            before: $before,
            page: 1,
            perPage: 20,
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        );

        $this->assertSame('event-123', $dto->eventId);
        $this->assertSame('user', $dto->actorType);
        $this->assertSame(42, $dto->actorId);
        $this->assertSame('resource', $dto->targetType);
        $this->assertSame(100, $dto->targetId);
        $this->assertSame('create', $dto->action);
        $this->assertSame('corr-456', $dto->correlationId);
        $this->assertSame($after, $dto->after);
        $this->assertSame($before, $dto->before);
        $this->assertSame(1, $dto->page);
        $this->assertSame(20, $dto->perPage);
        $this->assertSame('occurred_at', $dto->sortBy);
        $this->assertSame('DESC', $dto->sortDirection);
    }

    public function testDefaultsTrimAndBlankStringToNull(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: '  ',
            actorType: ' ',
            actorId: null,
            targetType: '',
            targetId: null,
            action: '   ',
            correlationId: ' ',
        );

        $this->assertNull($dto->eventId);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->action);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->page);
        $this->assertNull($dto->perPage);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);
    }

    public function testTrimPreservesValidValues(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: '  valid-id  ',
            actorType: ' user '
        );

        $this->assertSame('valid-id', $dto->eventId);
        $this->assertSame('user', $dto->actorType);
    }

    public function testRawPageAndPerPage(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            page: ' 02 ',
            perPage: '300'
        );

        $this->assertSame(' 02 ', $dto->page);
        $this->assertSame('300', $dto->perPage);
    }

    public function testZeroOrNegativeActorIdThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query ID: actorId');
        new AuthoritativeAuditAdminQueryRequestDTO(actorId: 0);
    }

    public function testNegativeTargetIdThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query ID: targetId');
        new AuthoritativeAuditAdminQueryRequestDTO(targetId: -5);
    }

    public function testEqualDateBoundariesAreValid(): void
    {
        $date = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'));
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(after: $date, before: $date);

        $this->assertSame($date, $dto->after);
        $this->assertSame($date, $dto->before);
    }

    public function testAfterGreaterThanBeforeThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before');
        new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-02 00:00:00'),
            before: new DateTimeImmutable('2024-01-01 00:00:00')
        );
    }

    public function testExactMaximumAcceptedLengthsAreValid(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: str_repeat('a', 36),
            actorType: str_repeat('b', 32),
            targetType: str_repeat('c', 64),
            action: str_repeat('d', 128),
            correlationId: str_repeat('e', 36)
        );

        $this->assertSame(str_repeat('a', 36), $dto->eventId);
        $this->assertSame(str_repeat('b', 32), $dto->actorType);
        $this->assertSame(str_repeat('c', 64), $dto->targetType);
        $this->assertSame(str_repeat('d', 128), $dto->action);
        $this->assertSame(str_repeat('e', 36), $dto->correlationId);
    }

    public function testInvalidEventIdLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: eventId');
        new AuthoritativeAuditAdminQueryRequestDTO(eventId: str_repeat('a', 37));
    }

    public function testInvalidActorTypeLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: actorType');
        new AuthoritativeAuditAdminQueryRequestDTO(actorType: str_repeat('a', 33));
    }

    public function testInvalidTargetTypeLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: targetType');
        new AuthoritativeAuditAdminQueryRequestDTO(targetType: str_repeat('a', 65));
    }

    public function testInvalidActionLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: action');
        new AuthoritativeAuditAdminQueryRequestDTO(action: str_repeat('a', 129));
    }

    public function testInvalidCorrelationIdLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: correlationId');
        new AuthoritativeAuditAdminQueryRequestDTO(correlationId: str_repeat('a', 37));
    }

    public function testInvalidUtf8ThrowsWithoutMbstring(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query UTF-8 encoding: eventId');
        $invalidUtf8 = "invalid\xFFutf8";
        new AuthoritativeAuditAdminQueryRequestDTO(eventId: $invalidUtf8);
    }

    public function testSortByInvalidValueReturnsNull(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'invalid_sort');
        $this->assertNull($dto->sortBy);
    }

    public function testSortDirectionInvalidValueReturnsNull(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(sortDirection: 'ASDF');
        $this->assertNull($dto->sortDirection);
    }

    public function testSortByOverlongLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: sortBy');
        new AuthoritativeAuditAdminQueryRequestDTO(sortBy: str_repeat('a', 65));
    }

    public function testSortDirectionOverlongLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: sortDirection');
        new AuthoritativeAuditAdminQueryRequestDTO(sortDirection: str_repeat('a', 5));
    }

    public function testJsonSerializeExactKeysAndValues(): void
    {
        $after = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $before = new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC'));

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: 'event-123',
            actorType: 'user',
            actorId: 42,
            targetType: 'resource',
            targetId: 100,
            action: 'create',
            correlationId: 'corr-456',
            after: $after,
            before: $before,
            page: 1,
            perPage: 20,
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        );

        $json = $dto->jsonSerialize();
        $this->assertSame([
            'eventId' => 'event-123',
            'actorType' => 'user',
            'actorId' => 42,
            'targetType' => 'resource',
            'targetId' => 100,
            'action' => 'create',
            'correlationId' => 'corr-456',
            'after' => '2024-01-01T00:00:00+00:00',
            'before' => '2024-01-02T00:00:00+00:00',
            'page' => 1,
            'perPage' => 20,
            'sortBy' => 'occurred_at',
            'sortDirection' => 'DESC',
        ], $json);
    }

    public function testTimezoneNotModified(): void
    {
        $after = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('America/New_York'));
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(after: $after);

        /** @var DateTimeImmutable $afterDt */
        $afterDt = $dto->after;
        $this->assertSame('America/New_York', $afterDt->getTimezone()->getName());
    }
}
