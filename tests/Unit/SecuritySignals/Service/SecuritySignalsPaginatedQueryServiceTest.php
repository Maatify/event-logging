<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Service;

use DateTimeImmutable;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsQueryInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Service\SecuritySignalsPaginatedQueryService;
use PHPUnit\Framework\TestCase;

class SecuritySignalsPaginatedQueryServiceTest extends TestCase
{
    public function testItReturnsEmptyPageWhenLimitIsZeroOrNegative(): void
    {
        $repo = $this->createMock(SecuritySignalsQueryInterface::class);
        $repo->expects($this->never())->method('find');

        $service = new SecuritySignalsPaginatedQueryService($repo);

        $page = $service->findPage(new SecuritySignalsQueryDTO(limit: 0));

        $this->assertEmpty($page->items);
        $this->assertNull($page->nextCursor);
        $this->assertFalse($page->hasMore);
    }

    public function testItReturnsHasMoreTrueExcludesExtraRecordAndBuildsNextCursorFromLastActualItem(): void
    {
        $repo = $this->createMock(SecuritySignalsQueryInterface::class);

        $item1 = $this->createDummyItem(1, '2023-10-15T10:00:00+00:00');
        $item2 = $this->createDummyItem(2, '2023-10-15T10:01:00+00:00');
        $extra = $this->createDummyItem(3, '2023-10-15T10:02:00+00:00');

        $repo->expects($this->once())
            ->method('find')
            ->with($this->callback(static fn (SecuritySignalsQueryDTO $q): bool => $q->limit === 3))
            ->willReturn([$item1, $item2, $extra]);

        $service = new SecuritySignalsPaginatedQueryService($repo);
        $page = $service->findPage(new SecuritySignalsQueryDTO(limit: 2));

        $this->assertTrue($page->hasMore);
        $this->assertSame([$item1, $item2], $page->items);
        $this->assertNotContains($extra, $page->items, true);
        $this->assertNotNull($page->nextCursor);
        $this->assertSame($item2->id, $page->nextCursor->id);
        $this->assertSame($item2->occurredAt, $page->nextCursor->occurredAt);
    }

    public function testItReturnsHasMoreFalseWhenResultsAreLessThanOrEqualLimit(): void
    {
        $repo = $this->createMock(SecuritySignalsQueryInterface::class);

        $item1 = $this->createDummyItem(1);
        $item2 = $this->createDummyItem(2);

        $repo->expects($this->once())
            ->method('find')
            ->willReturn([$item1, $item2]);

        $service = new SecuritySignalsPaginatedQueryService($repo);
        $page = $service->findPage(new SecuritySignalsQueryDTO(limit: 2));

        $this->assertFalse($page->hasMore);
        $this->assertSame([$item1, $item2], $page->items);
        $this->assertNull($page->nextCursor);
    }

    public function testItPassesAllOriginalFiltersToInternalQueryAndDoesNotMutateOriginalQuery(): void
    {
        $repo = $this->createMock(SecuritySignalsQueryInterface::class);

        $afterDate = new DateTimeImmutable('2023-10-01T00:00:00+00:00');
        $beforeDate = new DateTimeImmutable('2023-10-31T23:59:59+00:00');
        $cursorDate = new DateTimeImmutable('2023-10-15T12:00:00+00:00');

        $originalQuery = new SecuritySignalsQueryDTO(
            after: $afterDate,
            before: $beforeDate,
            actorType: 'admin',
            actorId: 99,
            signalType: 'auth.failed',
            severity: 'high',
            requestId: 'req-123',
            correlationId: 'corr-456',
            cursorOccurredAt: $cursorDate,
            cursorId: 100,
            limit: 5
        );

        $repo->expects($this->once())
            ->method('find')
            ->with($this->callback(function (SecuritySignalsQueryDTO $q) use ($afterDate, $beforeDate, $cursorDate): bool {
                return $q->after === $afterDate
                    && $q->before === $beforeDate
                    && $q->actorType === 'admin'
                    && $q->actorId === 99
                    && $q->signalType === 'auth.failed'
                    && $q->severity === 'high'
                    && $q->requestId === 'req-123'
                    && $q->correlationId === 'corr-456'
                    && $q->cursorOccurredAt === $cursorDate
                    && $q->cursorId === 100
                    && $q->limit === 6;
            }))
            ->willReturn([]);

        $service = new SecuritySignalsPaginatedQueryService($repo);
        $service->findPage($originalQuery);

        $this->assertSame(5, $originalQuery->limit);
        $this->assertSame($afterDate, $originalQuery->after);
        $this->assertSame($beforeDate, $originalQuery->before);
        $this->assertSame('admin', $originalQuery->actorType);
        $this->assertSame(99, $originalQuery->actorId);
        $this->assertSame('auth.failed', $originalQuery->signalType);
        $this->assertSame('high', $originalQuery->severity);
        $this->assertSame('req-123', $originalQuery->requestId);
        $this->assertSame('corr-456', $originalQuery->correlationId);
        $this->assertSame($cursorDate, $originalQuery->cursorOccurredAt);
        $this->assertSame(100, $originalQuery->cursorId);
    }

    public function testItDoesNotSwallowExceptions(): void
    {
        $repo = $this->createMock(SecuritySignalsQueryInterface::class);
        $repo->expects($this->once())
            ->method('find')
            ->willThrowException(new SecuritySignalsStorageException('DB error'));

        $service = new SecuritySignalsPaginatedQueryService($repo);

        $this->expectException(SecuritySignalsStorageException::class);
        $service->findPage(new SecuritySignalsQueryDTO(limit: 2));
    }

    private function createDummyItem(int $id, string $occurredAt = '2023-10-15T10:00:00+00:00'): SecuritySignalsViewDTO
    {
        return new SecuritySignalsViewDTO(
            id: $id,
            eventId: 'ev-'.$id,
            actorType: 'user',
            actorId: 1,
            signalType: 'login.failed',
            severity: 'medium',
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            metadata: null,
            occurredAt: new DateTimeImmutable($occurredAt)
        );
    }
}
