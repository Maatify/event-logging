<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Service;

use DateTimeImmutable;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Service\AuthoritativeAuditPaginatedQueryService;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditPaginatedQueryServiceTest extends TestCase
{
    public function testItReturnsEmptyPageWhenLimitIsZeroOrNegative(): void
    {
        $repo = $this->createMock(AuthoritativeAuditQueryInterface::class);
        $repo->expects($this->never())->method('find');

        $page = (new AuthoritativeAuditPaginatedQueryService($repo))->findPage(new AuthoritativeAuditQueryDTO(limit: 0));

        $this->assertEmpty($page->items);
        $this->assertNull($page->nextCursor);
        $this->assertFalse($page->hasMore);
    }

    public function testItReturnsHasMoreTrueAndNextCursorWhenExtraRecordIsFound(): void
    {
        $repo = $this->createMock(AuthoritativeAuditQueryInterface::class);
        $item1 = $this->createDummyItem(1);
        $item2 = $this->createDummyItem(2);
        $item3 = $this->createDummyItem(3);

        $repo->expects($this->once())
            ->method('find')
            ->with($this->callback(static fn (AuthoritativeAuditQueryDTO $q): bool => $q->limit === 3))
            ->willReturn([$item1, $item2, $item3]);

        $page = (new AuthoritativeAuditPaginatedQueryService($repo))->findPage(new AuthoritativeAuditQueryDTO(limit: 2));

        $this->assertTrue($page->hasMore);
        $this->assertCount(2, $page->items);
        $this->assertSame($item1, $page->items[0]);
        $this->assertSame($item2, $page->items[1]);
        $this->assertNotContains($item3, $page->items, true);
        $this->assertNotNull($page->nextCursor);
        $this->assertSame(2, $page->nextCursor->id);
        $this->assertSame($item2->occurredAt, $page->nextCursor->occurredAt);
    }

    public function testItReturnsHasMoreFalseWhenResultsAreLessThanOrEqualLimit(): void
    {
        $repo = $this->createMock(AuthoritativeAuditQueryInterface::class);
        $item1 = $this->createDummyItem(1);
        $item2 = $this->createDummyItem(2);

        $repo->expects($this->once())->method('find')->willReturn([$item1, $item2]);

        $page = (new AuthoritativeAuditPaginatedQueryService($repo))->findPage(new AuthoritativeAuditQueryDTO(limit: 2));

        $this->assertFalse($page->hasMore);
        $this->assertCount(2, $page->items);
        $this->assertNull($page->nextCursor);
    }

    public function testItPassesAllOriginalFiltersToInternalQueryAndDoesNotMutateOriginalQuery(): void
    {
        $repo = $this->createMock(AuthoritativeAuditQueryInterface::class);
        $afterDate = new DateTimeImmutable('2023-10-01T00:00:00+00:00');
        $beforeDate = new DateTimeImmutable('2023-10-31T23:59:59+00:00');
        $cursorDate = new DateTimeImmutable('2023-10-15T12:00:00+00:00');

        $originalQuery = new AuthoritativeAuditQueryDTO(
            after: $afterDate,
            before: $beforeDate,
            actorType: 'admin',
            actorId: 99,
            targetType: 'post',
            targetId: 42,
            action: 'post.deleted',
            correlationId: 'corr-456',
            cursorOccurredAt: $cursorDate,
            cursorId: 100,
            limit: 5
        );

        $repo->expects($this->once())
            ->method('find')
            ->with($this->callback(static function (AuthoritativeAuditQueryDTO $q) use ($afterDate, $beforeDate, $cursorDate): bool {
                return $q->after === $afterDate
                    && $q->before === $beforeDate
                    && $q->actorType === 'admin'
                    && $q->actorId === 99
                    && $q->targetType === 'post'
                    && $q->targetId === 42
                    && $q->action === 'post.deleted'
                    && $q->correlationId === 'corr-456'
                    && $q->cursorOccurredAt === $cursorDate
                    && $q->cursorId === 100
                    && $q->limit === 6;
            }))
            ->willReturn([]);

        (new AuthoritativeAuditPaginatedQueryService($repo))->findPage($originalQuery);

        $this->assertSame(5, $originalQuery->limit);
        $this->assertSame('admin', $originalQuery->actorType);
        $this->assertSame('post.deleted', $originalQuery->action);
    }

    public function testItDoesNotSwallowExceptions(): void
    {
        $repo = $this->createMock(AuthoritativeAuditQueryInterface::class);
        $repo->expects($this->once())
            ->method('find')
            ->willThrowException(new AuthoritativeAuditStorageException('DB error'));

        $this->expectException(AuthoritativeAuditStorageException::class);
        (new AuthoritativeAuditPaginatedQueryService($repo))->findPage(new AuthoritativeAuditQueryDTO(limit: 2));
    }

    private function createDummyItem(int $id): AuthoritativeAuditViewDTO
    {
        return new AuthoritativeAuditViewDTO(
            id: $id,
            eventId: 'ev-'.$id,
            actorType: 'user',
            actorId: 1,
            action: 'created',
            targetType: 'post',
            targetId: 1,
            ipAddress: null,
            userAgent: null,
            correlationId: null,
            changes: null,
            occurredAt: new DateTimeImmutable('2023-10-15T10:00:00+00:00')
        );
    }
}
