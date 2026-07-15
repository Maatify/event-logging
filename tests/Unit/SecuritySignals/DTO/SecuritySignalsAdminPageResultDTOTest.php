<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminPageResultDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use PHPUnit\Framework\TestCase;

final class SecuritySignalsAdminPageResultDTOTest extends TestCase
{
    public function testConstructorIteratorSerializationAndNoRootId(): void
    {
        $item = new SecuritySignalsViewDTO(
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

        $result = new SecuritySignalsAdminPageResultDTO(
            items: [$item],
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

        $this->assertSame([$item], iterator_to_array($result));
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
        ], array_keys($result->jsonSerialize()));
        $this->assertArrayNotHasKey('id', $result->jsonSerialize());
        $this->assertSame('DESC', $result->sortDirection);

        $empty = new SecuritySignalsAdminPageResultDTO([], 1, 20, 0, 0, 0, false, false, 'occurred_at', 'DESC');
        $this->assertSame([], iterator_to_array($empty));
    }
}
