<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Service;

use DateTimeImmutable;
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuditTrail\Service\AuditTrailPaginatedQueryService;
use PHPUnit\Framework\TestCase;

class AuditTrailPaginatedQueryServiceTest extends TestCase
{
    private function createDummyItem(int $id): AuditTrailViewDTO
    {
        return new AuditTrailViewDTO(
            id: $id,
            eventId: 'ev-'.$id,
            actorType: 'user',
            actorId: 1,
            eventKey: 'created',
            entityType: 'post',
            entityId: 1,
            subjectType: null,
            subjectId: null,
            referrerRouteName: null,
            referrerPath: null,
            referrerHost: null,
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            metadata: null,
            occurredAt: new DateTimeImmutable('2023-10-15T10:00:00+00:00')
        );
    }

    public function testItReturnsEmptyPageWhenLimitIsZeroOrNegative(): void
    {
        $repo = $this->createMock(AuditTrailQueryInterface::class);
        $repo->expects($this->never())->method('find');

        $service = new AuditTrailPaginatedQueryService($repo);

        $query = new AuditTrailQueryDTO(limit: 0);
        $page = $service->findPage($query);

        $this->assertEmpty($page->items);
        $this->assertNull($page->nextCursor);
        $this->assertFalse($page->hasMore);
    }

    public function testItReturnsHasMoreTrueAndNextCursorWhenExtraRecordIsFound(): void
    {
        $repo = $this->createMock(AuditTrailQueryInterface::class);

        $item1 = $this->createDummyItem(1);
        $item2 = $this->createDummyItem(2);
        $item3 = $this->createDummyItem(3);

        $repo->expects($this->once())
            ->method('find')
            ->with($this->callback(function (AuditTrailQueryDTO $q) {
                return $q->limit === 3; // Original limit 2 + 1
            }))
            ->willReturn([$item1, $item2, $item3]);

        $service = new AuditTrailPaginatedQueryService($repo);

        $query = new AuditTrailQueryDTO(limit: 2);
        $page = $service->findPage($query);

        $this->assertTrue($page->hasMore);
        $this->assertCount(2, $page->items);
        $this->assertSame($item1, $page->items[0]);
        $this->assertSame($item2, $page->items[1]);

        $this->assertNotNull($page->nextCursor);
        $this->assertSame(2, $page->nextCursor->id);
    }

    public function testItReturnsHasMoreFalseWhenResultsAreLessThanOrEqualLimit(): void
    {
        $repo = $this->createMock(AuditTrailQueryInterface::class);

        $item1 = $this->createDummyItem(1);
        $item2 = $this->createDummyItem(2);

        $repo->expects($this->once())
            ->method('find')
            ->willReturn([$item1, $item2]);

        $service = new AuditTrailPaginatedQueryService($repo);

        $query = new AuditTrailQueryDTO(limit: 2);
        $page = $service->findPage($query);

        $this->assertFalse($page->hasMore);
        $this->assertCount(2, $page->items);
        $this->assertNull($page->nextCursor);
    }

    public function testItDoesNotSwallowExceptions(): void
    {
        $repo = $this->createMock(AuditTrailQueryInterface::class);
        $repo->expects($this->once())
            ->method('find')
            ->willThrowException(new AuditTrailStorageException("DB error"));

        $service = new AuditTrailPaginatedQueryService($repo);

        $query = new AuditTrailQueryDTO(limit: 2);

        $this->expectException(AuditTrailStorageException::class);
        $service->findPage($query);
    }
}
