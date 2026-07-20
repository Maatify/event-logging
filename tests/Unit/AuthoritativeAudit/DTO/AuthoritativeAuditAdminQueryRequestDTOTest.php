<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminQueryRequestDTOTest extends TestCase
{
    public function testConstructNormalizesValuesAndSerializesExactOrder(): void
    {
        $after = new DateTimeImmutable('2024-01-01 00:00:00.123456', new DateTimeZone('Africa/Cairo'));
        $before = new DateTimeImmutable('2024-01-02 00:00:00.654321', new DateTimeZone('Africa/Cairo'));

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: '  event-123  ',
            actorType: '  user  ',
            actorId: 42,
            targetType: '  resource  ',
            targetId: 100,
            action: '  create  ',
            correlationId: '  corr-456  ',
            after: $after,
            before: $before,
            page: '003',
            perPage: 20,
            sortBy: '  occurred_at  ',
            sortDirection: '  asc  '
        );

        self::assertSame('event-123', $dto->eventId);
        self::assertSame('user', $dto->actorType);
        self::assertSame(42, $dto->actorId);
        self::assertSame('resource', $dto->targetType);
        self::assertSame(100, $dto->targetId);
        self::assertSame('create', $dto->action);
        self::assertSame('corr-456', $dto->correlationId);
        self::assertSame($after, $dto->after);
        self::assertSame($before, $dto->before);
        self::assertSame('003', $dto->page);
        self::assertSame(20, $dto->perPage);
        self::assertSame('occurred_at', $dto->sortBy);
        self::assertSame('ASC', $dto->sortDirection);

        $serialized = $dto->jsonSerialize();
        self::assertSame([
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
        ], array_keys($serialized));
        self::assertSame([
            'eventId' => 'event-123',
            'actorType' => 'user',
            'actorId' => 42,
            'targetType' => 'resource',
            'targetId' => 100,
            'action' => 'create',
            'correlationId' => 'corr-456',
            'after' => $after->format(DATE_ATOM),
            'before' => $before->format(DATE_ATOM),
            'page' => '003',
            'perPage' => 20,
            'sortBy' => 'occurred_at',
            'sortDirection' => 'ASC',
        ], $serialized);
    }

    public function testEmptyStringsAndUnsupportedShortSortValuesNormalizeToNull(): void
    {
        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: '   ',
            actorType: "\t",
            targetType: "\n",
            action: '',
            correlationId: '  ',
            sortBy: 'id',
            sortDirection: 'up'
        );

        self::assertNull($dto->eventId);
        self::assertNull($dto->actorType);
        self::assertNull($dto->targetType);
        self::assertNull($dto->action);
        self::assertNull($dto->correlationId);
        self::assertNull($dto->sortBy);
        self::assertNull($dto->sortDirection);
    }

    public function testEqualDateBoundsAreAccepted(): void
    {
        $bound = new DateTimeImmutable('2024-01-01 00:00:00.000000', new DateTimeZone('UTC'));

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(after: $bound, before: $bound);

        self::assertSame($bound, $dto->after);
        self::assertSame($bound, $dto->before);
    }

    #[DataProvider('invalidIdProvider')]
    public function testInvalidIdsThrow(string $field, int $value): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid AuthoritativeAudit Admin Query ID: {$field}");

        match ($field) {
            'actorId' => new AuthoritativeAuditAdminQueryRequestDTO(actorId: $value),
            'targetId' => new AuthoritativeAuditAdminQueryRequestDTO(targetId: $value),
            default => self::fail("Unsupported ID field: {$field}"),
        };
    }

    /** @return array<string, array{string, int}> */
    public static function invalidIdProvider(): array
    {
        return [
            'zero actor ID' => ['actorId', 0],
            'negative actor ID' => ['actorId', -1],
            'zero target ID' => ['targetId', 0],
            'negative target ID' => ['targetId', -1],
        ];
    }

    public function testInvalidDateRangeThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before');

        new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'))
        );
    }

    #[DataProvider('invalidLengthProvider')]
    public function testInvalidLengthsThrow(string $field, string $value): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid AuthoritativeAudit Admin Query length: {$field}");

        $this->constructWithStringField($field, $value);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidLengthProvider(): array
    {
        return [
            'event ID over 36 characters' => ['eventId', str_repeat('a', 37)],
            'actor type over 32 characters' => ['actorType', str_repeat('a', 33)],
            'target type over 64 characters' => ['targetType', str_repeat('a', 65)],
            'action over 128 characters' => ['action', str_repeat('a', 129)],
            'correlation ID over 36 characters' => ['correlationId', str_repeat('a', 37)],
            'sort key over 64 characters' => ['sortBy', str_repeat('a', 65)],
            'sort direction over 4 characters' => ['sortDirection', 'DESCX'],
        ];
    }

    #[DataProvider('invalidEncodingProvider')]
    public function testInvalidUtf8Throws(string $field): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid AuthoritativeAudit Admin Query UTF-8 encoding: {$field}");

        $this->constructWithStringField($field, "\xC3\x28");
    }

    /** @return array<string, array{string}> */
    public static function invalidEncodingProvider(): array
    {
        return [
            'event ID' => ['eventId'],
            'actor type' => ['actorType'],
            'target type' => ['targetType'],
            'action' => ['action'],
            'correlation ID' => ['correlationId'],
            'sort key' => ['sortBy'],
            'sort direction' => ['sortDirection'],
        ];
    }

    private function constructWithStringField(string $field, string $value): AuthoritativeAuditAdminQueryRequestDTO
    {
        return match ($field) {
            'eventId' => new AuthoritativeAuditAdminQueryRequestDTO(eventId: $value),
            'actorType' => new AuthoritativeAuditAdminQueryRequestDTO(actorType: $value),
            'targetType' => new AuthoritativeAuditAdminQueryRequestDTO(targetType: $value),
            'action' => new AuthoritativeAuditAdminQueryRequestDTO(action: $value),
            'correlationId' => new AuthoritativeAuditAdminQueryRequestDTO(correlationId: $value),
            'sortBy' => new AuthoritativeAuditAdminQueryRequestDTO(sortBy: $value),
            'sortDirection' => new AuthoritativeAuditAdminQueryRequestDTO(sortDirection: $value),
            default => self::fail("Unsupported field: {$field}"),
        };
    }
}
