<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminPageResultDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeEnum;
use PHPUnit\Framework\TestCase;

final class BehaviorTraceAdminPageResultDTOTest extends TestCase
{
    public function testIteratesAndSerializesUsingItemsKey(): void
    {
        $item = new BehaviorTraceEventDTO(
            id: 1,
            eventId: 'event-1',
            action: 'view',
            entityType: 'document',
            entityId: 20,
            context: new BehaviorTraceContextDTO(
                actorType: BehaviorTraceActorTypeEnum::USER,
                actorId: 10,
                correlationId: null,
                requestId: null,
                routeName: null,
                ipAddress: null,
                userAgent: null,
                occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            ),
            metadata: null,
        );

        $result = new BehaviorTraceAdminPageResultDTO(
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
        $serialized = $result->jsonSerialize();
        $this->assertArrayHasKey('items', $serialized);
        $this->assertArrayNotHasKey('data', $serialized);
        $this->assertSame([$item], $serialized['items']);
        $this->assertSame('DESC', $serialized['sortDirection']);
    }
}
