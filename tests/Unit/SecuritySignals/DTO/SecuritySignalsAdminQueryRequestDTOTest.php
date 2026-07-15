<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;

final class SecuritySignalsAdminQueryRequestDTOTest extends TestCase
{
    public function testConstructorParameterOrderAndDefaults(): void
    {
        $constructor = new ReflectionMethod(SecuritySignalsAdminQueryRequestDTO::class, '__construct');

        $this->assertSame([
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'requestId',
            'correlationId',
            'after',
            'before',
            'page',
            'perPage',
            'sortBy',
            'sortDirection',
        ], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));

        $dto = new SecuritySignalsAdminQueryRequestDTO();

        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->signalType);
        $this->assertNull($dto->severity);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->after);
        $this->assertNull($dto->before);
        $this->assertNull($dto->page);
        $this->assertNull($dto->perPage);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);
    }

    public function testExactJsonKeyOrderNullableValuesAndPagePassthrough(): void
    {
        $dto = new SecuritySignalsAdminQueryRequestDTO(page: ' 02 ', perPage: '300');
        $serialized = $dto->jsonSerialize();

        $this->assertSame([
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'requestId',
            'correlationId',
            'after',
            'before',
            'page',
            'perPage',
            'sortBy',
            'sortDirection',
        ], array_keys($serialized));
        $this->assertNull($serialized['actorType']);
        $this->assertNull($serialized['actorId']);
        $this->assertSame(' 02 ', $dto->page);
        $this->assertSame('300', $dto->perPage);
    }

    public function testTrimmingEmptyStringNormalizationAndDateAtomSerialization(): void
    {
        $after = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Africa/Cairo'));
        $before = new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('Africa/Cairo'));

        $dto = new SecuritySignalsAdminQueryRequestDTO(
            actorType: ' user ',
            signalType: " \t",
            severity: ' high ',
            requestId: ' req ',
            correlationId: ' corr ',
            after: $after,
            before: $before,
        );

        $this->assertSame('user', $dto->actorType);
        $this->assertNull($dto->signalType);
        $this->assertSame('high', $dto->severity);
        $this->assertSame('req', $dto->requestId);
        $this->assertSame('corr', $dto->correlationId);
        $this->assertSame($after->format(DATE_ATOM), $dto->jsonSerialize()['after']);
        $this->assertSame($before->format(DATE_ATOM), $dto->jsonSerialize()['before']);
    }

    public function testActorFiltersAreIndependentAndIdsMustBePositive(): void
    {
        $this->assertSame(10, (new SecuritySignalsAdminQueryRequestDTO(actorId: 10))->actorId);
        $this->assertSame('admin', (new SecuritySignalsAdminQueryRequestDTO(actorType: 'admin'))->actorType);
        $this->assertSame(20, (new SecuritySignalsAdminQueryRequestDTO(actorType: 'admin', actorId: 20))->actorId);
        $this->assertNull((new SecuritySignalsAdminQueryRequestDTO())->actorId);

        $this->expectException(SecuritySignalsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SecuritySignals Admin Query ID: actorId');
        new SecuritySignalsAdminQueryRequestDTO(actorId: 0);
    }

    public function testNegativeActorIdIsRejected(): void
    {
        $this->expectException(SecuritySignalsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SecuritySignals Admin Query ID: actorId');
        new SecuritySignalsAdminQueryRequestDTO(actorId: -1);
    }

    public function testDateRangeRulesAndSortNormalization(): void
    {
        $equal = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $this->assertSame($equal, (new SecuritySignalsAdminQueryRequestDTO(after: $equal, before: $equal))->after);

        $valid = new SecuritySignalsAdminQueryRequestDTO(sortBy: ' occurred_at ', sortDirection: ' asc ');
        $this->assertSame('occurred_at', $valid->sortBy);
        $this->assertSame('ASC', $valid->sortDirection);

        $invalid = new SecuritySignalsAdminQueryRequestDTO(sortBy: 'id', sortDirection: 'bad');
        $this->assertNull($invalid->sortBy);
        $this->assertNull($invalid->sortDirection);

        $this->expectException(SecuritySignalsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SecuritySignals Admin Query date range: after must be before or equal to before');
        new SecuritySignalsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
        );
    }

    public function testEveryMaximumLengthBoundaryIsAccepted(): void
    {
        $dto = new SecuritySignalsAdminQueryRequestDTO(
            actorType: str_repeat('a', 32),
            signalType: str_repeat('b', 100),
            severity: str_repeat('c', 16),
            requestId: str_repeat('d', 64),
            correlationId: str_repeat('e', 36),
            sortBy: str_repeat('f', 64),
            sortDirection: str_repeat('g', 4),
        );

        $this->assertSame(str_repeat('a', 32), $dto->actorType);
        $this->assertSame(str_repeat('b', 100), $dto->signalType);
        $this->assertSame(str_repeat('c', 16), $dto->severity);
        $this->assertSame(str_repeat('d', 64), $dto->requestId);
        $this->assertSame(str_repeat('e', 36), $dto->correlationId);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);
    }

    public function testEveryOverLimitStringIsRejectedIndependently(): void
    {
        $this->assertInvalidLength('actorType', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(actorType: str_repeat('a', 33)));
        $this->assertInvalidLength('signalType', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(signalType: str_repeat('b', 101)));
        $this->assertInvalidLength('severity', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(severity: str_repeat('c', 17)));
        $this->assertInvalidLength('requestId', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(requestId: str_repeat('d', 65)));
        $this->assertInvalidLength('correlationId', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(correlationId: str_repeat('e', 37)));
        $this->assertInvalidLength('sortBy', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(sortBy: str_repeat('f', 65)));
        $this->assertInvalidLength('sortDirection', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(sortDirection: 'right'));
    }

    public function testInvalidUtf8IsRejectedWithoutMbstringIncludingSortFields(): void
    {
        $this->assertInvalidEncoding('actorType', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(actorType: "\xB1\x31"));
        $this->assertInvalidEncoding('sortBy', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(sortBy: "\xB1\x31"));
        $this->assertInvalidEncoding('sortDirection', static fn (): SecuritySignalsAdminQueryRequestDTO => new SecuritySignalsAdminQueryRequestDTO(sortDirection: "\xB1\x31"));
    }

    /** @param callable(): SecuritySignalsAdminQueryRequestDTO $factory */
    private function assertInvalidLength(string $field, callable $factory): void
    {
        try {
            $factory();
            self::fail('Expected invalid length for ' . $field);
        } catch (SecuritySignalsAdminQueryInvalidArgumentException $exception) {
            $this->assertSame('Invalid SecuritySignals Admin Query length: ' . $field, $exception->getMessage());
        }
    }

    /** @param callable(): SecuritySignalsAdminQueryRequestDTO $factory */
    private function assertInvalidEncoding(string $field, callable $factory): void
    {
        try {
            $factory();
            self::fail('Expected invalid encoding for ' . $field);
        } catch (SecuritySignalsAdminQueryInvalidArgumentException $exception) {
            $this->assertSame('Invalid SecuritySignals Admin Query UTF-8 encoding: ' . $field, $exception->getMessage());
        }
    }
}
