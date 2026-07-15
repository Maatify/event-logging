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
    public function testConstructorOrderDefaultsAndJsonKeyOrder(): void
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

        $dto = new SecuritySignalsAdminQueryRequestDTO(page: ' 02 ', perPage: '300');

        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertSame(' 02 ', $dto->page);
        $this->assertSame('300', $dto->perPage);
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
        ], array_keys($dto->jsonSerialize()));
    }

    public function testTrimsNullableStringsConvertsEmptyStringsToNullAndSerializesDates(): void
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

    public function testRejectsNegativeIdOverlongStringsAndInvalidUtf8(): void
    {
        try {
            new SecuritySignalsAdminQueryRequestDTO(actorId: -1);
            $this->fail('Expected invalid id exception.');
        } catch (SecuritySignalsAdminQueryInvalidArgumentException $exception) {
            $this->assertSame('Invalid SecuritySignals Admin Query ID: actorId', $exception->getMessage());
        }

        try {
            new SecuritySignalsAdminQueryRequestDTO(actorType: str_repeat('س', 33));
            $this->fail('Expected invalid length exception.');
        } catch (SecuritySignalsAdminQueryInvalidArgumentException $exception) {
            $this->assertSame('Invalid SecuritySignals Admin Query length: actorType', $exception->getMessage());
        }

        $this->expectException(SecuritySignalsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SecuritySignals Admin Query UTF-8 encoding: actorType');
        new SecuritySignalsAdminQueryRequestDTO(actorType: "\xB1\x31");
    }

    public function testMaximumLengthBoundariesAndOverLimitCases(): void
    {
        $valid = new SecuritySignalsAdminQueryRequestDTO(
            actorType: str_repeat('a', 32),
            signalType: str_repeat('b', 100),
            severity: str_repeat('c', 16),
            requestId: str_repeat('d', 64),
            correlationId: str_repeat('e', 36),
            sortBy: str_repeat('f', 64),
            sortDirection: str_repeat('g', 4),
        );

        $this->assertSame(str_repeat('a', 32), $valid->actorType);
        $this->assertNull($valid->sortBy);
        $this->assertNull($valid->sortDirection);

        $this->expectException(SecuritySignalsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SecuritySignals Admin Query length: signalType');
        new SecuritySignalsAdminQueryRequestDTO(signalType: str_repeat('b', 101));
    }

    public function testDateRangeAndSortNormalization(): void
    {
        $equal = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $this->assertSame($equal, (new SecuritySignalsAdminQueryRequestDTO(after: $equal, before: $equal))->after);

        $valid = new SecuritySignalsAdminQueryRequestDTO(sortBy: ' occurred_at ', sortDirection: ' asc ');
        $this->assertSame('occurred_at', $valid->sortBy);
        $this->assertSame('ASC', $valid->sortDirection);

        $invalid = new SecuritySignalsAdminQueryRequestDTO(sortBy: 'id', sortDirection: 'bad');
        $this->assertNull($invalid->sortBy);
        $this->assertNull($invalid->sortDirection);

        try {
            new SecuritySignalsAdminQueryRequestDTO(sortDirection: 'sideways');
            $this->fail('Expected invalid length exception.');
        } catch (SecuritySignalsAdminQueryInvalidArgumentException $exception) {
            $this->assertSame('Invalid SecuritySignals Admin Query length: sortDirection', $exception->getMessage());
        }

        $this->expectException(SecuritySignalsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SecuritySignals Admin Query date range: after must be before or equal to before');
        new SecuritySignalsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
        );
    }
}
