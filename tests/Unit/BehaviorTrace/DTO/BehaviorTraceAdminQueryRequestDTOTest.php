<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BehaviorTraceAdminQueryRequestDTOTest extends TestCase
{
    public function testDefaultsNormalizeToNullAndRawPageValuesRemainRaw(): void
    {
        $dto = new BehaviorTraceAdminQueryRequestDTO(page: ' 02 ', perPage: '300');

        $this->assertNull($dto->actorType);
        $this->assertSame(' 02 ', $dto->page);
        $this->assertSame('300', $dto->perPage);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);
    }

    public function testTrimsNullableStringsAndConvertsEmptyStringsToNull(): void
    {
        $dto = new BehaviorTraceAdminQueryRequestDTO(
            actorType: ' user ',
            action: " \t",
            entityType: ' document ',
            requestId: ' req-1 ',
            correlationId: ' corr-1 ',
        );

        $this->assertSame('user', $dto->actorType);
        $this->assertNull($dto->action);
        $this->assertSame('document', $dto->entityType);
        $this->assertSame('req-1', $dto->requestId);
        $this->assertSame('corr-1', $dto->correlationId);
    }

    public function testPairRulesRejectIdsWithoutTypes(): void
    {
        $this->expectException(BehaviorTraceAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BehaviorTrace Admin Query ID: actorId without actorType');

        new BehaviorTraceAdminQueryRequestDTO(actorId: 1);
    }

    public function testEntityPairRuleRejectsEntityIdWithoutEntityType(): void
    {
        $this->expectException(BehaviorTraceAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BehaviorTrace Admin Query ID: entityId without entityType');

        new BehaviorTraceAdminQueryRequestDTO(entityId: 1);
    }

    public function testRejectsZeroNegativeOverlongAndInvalidUtf8Values(): void
    {
        $this->expectException(BehaviorTraceAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BehaviorTrace Admin Query ID: actorId');
        new BehaviorTraceAdminQueryRequestDTO(actorType: 'user', actorId: 0);
    }

    public function testRejectsOverlongUtf8StringsByCharacterCount(): void
    {
        $this->expectException(BehaviorTraceAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BehaviorTrace Admin Query length: actorType');

        new BehaviorTraceAdminQueryRequestDTO(actorType: str_repeat('س', 33));
    }

    public function testRejectsInvalidUtf8EncodingWithoutMbstring(): void
    {
        $this->expectException(BehaviorTraceAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BehaviorTrace Admin Query UTF-8 encoding: actorType');

        new BehaviorTraceAdminQueryRequestDTO(actorType: "\xB1\x31");
    }

    public function testRejectsAfterGreaterThanBeforeButAcceptsEqualBoundaries(): void
    {
        $equal = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $dto = new BehaviorTraceAdminQueryRequestDTO(after: $equal, before: $equal);
        $this->assertSame($equal, $dto->after);
        $this->assertSame($equal, $dto->before);

        $this->expectException(BehaviorTraceAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BehaviorTrace Admin Query date range: after must be before or equal to before');
        new BehaviorTraceAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
        );
    }

    public function testSortNormalizationAllowsOnlyOccurredAtAndAscDesc(): void
    {
        $valid = new BehaviorTraceAdminQueryRequestDTO(sortBy: ' occurred_at ', sortDirection: ' asc ');
        $this->assertSame('occurred_at', $valid->sortBy);
        $this->assertSame('ASC', $valid->sortDirection);

        $invalid = new BehaviorTraceAdminQueryRequestDTO(sortBy: 'id', sortDirection: 'bad');
        $this->assertNull($invalid->sortBy);
        $this->assertNull($invalid->sortDirection);
    }

    public function testRejectsOverlongSortDirection(): void
    {
        $this->expectException(BehaviorTraceAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BehaviorTrace Admin Query length: sortDirection');

        new BehaviorTraceAdminQueryRequestDTO(sortDirection: 'sideways');
    }

    public function testJsonSerializesDatesWithDateAtomAndDoesNotMutateDateObjects(): void
    {
        $after = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Africa/Cairo'));
        $before = new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('Africa/Cairo'));

        $dto = new BehaviorTraceAdminQueryRequestDTO(after: $after, before: $before);

        $this->assertSame('Africa/Cairo', $after->getTimezone()->getName());
        $this->assertSame($after->format(DATE_ATOM), $dto->jsonSerialize()['after']);
        $this->assertSame($before->format(DATE_ATOM), $dto->jsonSerialize()['before']);
    }
}
