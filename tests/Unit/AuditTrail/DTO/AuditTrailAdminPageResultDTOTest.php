<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminPageResultDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;
use PHPUnit\Framework\TestCase;

final class AuditTrailAdminPageResultDTOTest extends TestCase
{
    public function testIteratesAndSerializesUsingItemsKey(): void
    {
        $item = new AuditTrailViewDTO(
            id: 1,
            eventId: 'event-1',
            actorType: 'user',
            actorId: 10,
            eventKey: 'view',
            entityType: 'document',
            entityId: 20,
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
            occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00')
        );

        $result = new AuditTrailAdminPageResultDTO(
            items: [$item],
            page: 1,
            perPage: 20,
            total: 1,
            filtered: 1,
            totalPages: 1,
            hasNext: false,
            hasPrevious: false,
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        );

        $this->assertSame([$item], iterator_to_array($result));
        $serialized = $result->jsonSerialize();
        $this->assertArrayHasKey('items', $serialized);
        $this->assertArrayNotHasKey('data', $serialized);
        $this->assertSame([$item], $serialized['items']);
        $this->assertSame('DESC', $serialized['sortDirection']);
    }
}
