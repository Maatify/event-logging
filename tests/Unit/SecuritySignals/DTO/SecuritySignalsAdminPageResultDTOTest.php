<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminPageResultDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use PHPUnit\Framework\TestCase;

final class SecuritySignalsAdminPageResultDTOTest extends TestCase
{
    public function testConstructorValuesArePreserved(): void
    {
        $item = $this->item();
        $result = new SecuritySignalsAdminPageResultDTO(
            items: [$item],
            page: 2,
            perPage: 20,
            total: 50,
            filtered: 25,
            totalPages: 2,
            hasNext: false,
            hasPrevious: true,
            sortBy: 'occurred_at',
            sortDirection: 'DESC',
        );

        $this->assertSame([$item], $result->items);
        $this->assertSame(2, $result->page);
        $this->assertSame(20, $result->perPage);
        $this->assertSame(50, $result->total);
        $this->assertSame(25, $result->filtered);
        $this->assertSame(2, $result->totalPages);
        $this->assertFalse($result->hasNext);
        $this->assertTrue($result->hasPrevious);
        $this->assertSame('occurred_at', $result->sortBy);
        $this->assertSame('DESC', $result->sortDirection);
    }

    public function testJsonSerializationUsesExactRootKeysAndOrderWithoutRootId(): void
    {
        $result = new SecuritySignalsAdminPageResultDTO(
            items: [$this->item()],
            page: 1,
            perPage: 20,
            total: 1,
            filtered: 1,
            totalPages: 1,
            hasNext: false,
            hasPrevious: false,
            sortBy: 'occurred_at',
            sortDirection: 'DESC',
        );

        $serialized = $result->jsonSerialize();

        $this->assertSame([
            'items',
            'page',
            'perPage',
            'total',
            'filtered',
            'totalPages',
            'hasNext',
            'hasPrevious',
            'sortBy',
            'sortDirection',
        ], array_keys($serialized));
        $this->assertArrayNotHasKey('id', $serialized);
    }

    public function testIteratorBehavior(): void
    {
        $item = $this->item();
        $result = new SecuritySignalsAdminPageResultDTO([$item], 1, 20, 1, 1, 1, false, false, 'occurred_at', 'DESC');

        $this->assertSame([$item], iterator_to_array($result));
    }

    public function testEmptyItemsAndCanonicalPaginationMetadata(): void
    {
        $result = new SecuritySignalsAdminPageResultDTO([], 1, 20, 0, 0, 0, false, false, 'occurred_at', 'DESC');

        $this->assertSame([], $result->items);
        $this->assertSame([], iterator_to_array($result));
        $this->assertSame(1, $result->page);
        $this->assertSame(20, $result->perPage);
        $this->assertSame(0, $result->total);
        $this->assertSame(0, $result->filtered);
        $this->assertSame(0, $result->totalPages);
        $this->assertFalse($result->hasNext);
        $this->assertFalse($result->hasPrevious);
    }

    private function item(): SecuritySignalsViewDTO
    {
        return new SecuritySignalsViewDTO(
            id: 1,
            eventId: 'event-1',
            actorType: 'admin',
            actorId: 10,
            signalType: 'login_failed',
            severity: 'HIGH',
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            metadata: null,
            occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        );
    }
}
