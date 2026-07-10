<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Service;

use DateTimeImmutable;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceQueryInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeEnum;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;
use Maatify\EventLogging\BehaviorTrace\Service\BehaviorTracePaginatedQueryService;
use PHPUnit\Framework\TestCase;

class BehaviorTracePaginatedQueryServiceTest extends TestCase
{
    private function createDummyItem(int $id, string $occurredAt = '2023-10-15T10:00:00+00:00'): BehaviorTraceEventDTO
    {
        return new BehaviorTraceEventDTO(
            id: $id,
            eventId: 'ev-'.$id,
            action: 'clicked',
            entityType: 'post',
            entityId: 1,
            context: new BehaviorTraceContextDTO(
                BehaviorTraceActorTypeEnum::USER,
                1,
                null,
                null,
                null,
                null,
                null,
                new DateTimeImmutable($occurredAt)
            ),
            metadata: null
        );
    }

    public function testItReturnsEmptyPageWhenLimitIsZeroOrNegative(): void
    {
        $repo = $this->createMock(BehaviorTraceQueryInterface::class);
        $repo->expects($this->never())->method('find');

        $page = (new BehaviorTracePaginatedQueryService($repo))->findPage(new BehaviorTraceQueryDTO(limit: 0));

        $this->assertSame([], $page->items);
        $this->assertNull($page->nextCursor);
        $this->assertFalse($page->hasMore);
    }

    public function testItReturnsHasMoreTrueRemovesExtraItemAndBuildsNextCursorFromLastPageItem(): void
    {
        $repo = $this->createMock(BehaviorTraceQueryInterface::class);
        $item1 = $this->createDummyItem(1, '2023-10-15T10:00:00+00:00');
        $item2 = $this->createDummyItem(2, '2023-10-15T09:00:00+00:00');
        $extra = $this->createDummyItem(3, '2023-10-15T08:00:00+00:00');

        $repo->expects($this->once())
            ->method('find')
            ->with($this->callback(fn (BehaviorTraceQueryDTO $q): bool => $q->limit === 3))
            ->willReturn([$item1, $item2, $extra]);

        $page = (new BehaviorTracePaginatedQueryService($repo))->findPage(new BehaviorTraceQueryDTO(limit: 2));

        $this->assertTrue($page->hasMore);
        $this->assertSame([$item1, $item2], $page->items);
        $this->assertNotContains($extra, $page->items, true);
        $this->assertNotNull($page->nextCursor);
        $this->assertSame(2, $page->nextCursor->id);
        $this->assertSame($item2->context->occurredAt, $page->nextCursor->occurredAt);
    }

    public function testItReturnsHasMoreFalseWhenResultsAreLessThanOrEqualLimit(): void
    {
        $repo = $this->createMock(BehaviorTraceQueryInterface::class);
        $item1 = $this->createDummyItem(1);
        $item2 = $this->createDummyItem(2);

        $repo->expects($this->once())->method('find')->willReturn([$item1, $item2]);

        $page = (new BehaviorTracePaginatedQueryService($repo))->findPage(new BehaviorTraceQueryDTO(limit: 2));

        $this->assertFalse($page->hasMore);
        $this->assertSame([$item1, $item2], $page->items);
        $this->assertNull($page->nextCursor);
    }

    public function testItPassesAllOriginalFiltersToInternalQueryAndDoesNotMutateOriginalQuery(): void
    {
        $repo = $this->createMock(BehaviorTraceQueryInterface::class);
        $after = new DateTimeImmutable('2023-10-01T00:00:00+00:00');
        $before = new DateTimeImmutable('2023-10-31T23:59:59+00:00');
        $cursor = new DateTimeImmutable('2023-10-15T12:00:00+00:00');

        $original = new BehaviorTraceQueryDTO(
            after: $after,
            before: $before,
            actorType: 'admin',
            actorId: 99,
            entityType: 'post',
            entityId: 42,
            action: 'deleted',
            requestId: 'req-123',
            correlationId: 'corr-456',
            cursorOccurredAt: $cursor,
            cursorId: 100,
            limit: 5
        );

        $repo->expects($this->once())
            ->method('find')
            ->with($this->callback(function (BehaviorTraceQueryDTO $q) use ($after, $before, $cursor): bool {
                return $q->after === $after
                    && $q->before === $before
                    && $q->actorType === 'admin'
                    && $q->actorId === 99
                    && $q->entityType === 'post'
                    && $q->entityId === 42
                    && $q->action === 'deleted'
                    && $q->requestId === 'req-123'
                    && $q->correlationId === 'corr-456'
                    && $q->cursorOccurredAt === $cursor
                    && $q->cursorId === 100
                    && $q->limit === 6;
            }))
            ->willReturn([]);

        (new BehaviorTracePaginatedQueryService($repo))->findPage($original);

        $this->assertSame(5, $original->limit);
        $this->assertSame('admin', $original->actorType);
    }

    public function testItDoesNotSwallowStorageExceptions(): void
    {
        $repo = $this->createMock(BehaviorTraceQueryInterface::class);
        $repo->expects($this->once())
            ->method('find')
            ->willThrowException(new BehaviorTraceStorageException('DB error'));

        $this->expectException(BehaviorTraceStorageException::class);

        (new BehaviorTracePaginatedQueryService($repo))->findPage(new BehaviorTraceQueryDTO(limit: 2));
    }
}
