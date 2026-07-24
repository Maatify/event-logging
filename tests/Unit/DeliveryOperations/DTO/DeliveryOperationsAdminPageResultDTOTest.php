<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminPageResultDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsViewDTO;
use PHPUnit\Framework\TestCase;

final class DeliveryOperationsAdminPageResultDTOTest extends TestCase
{
    public function testItSerializesAndIterates(): void
    {
        $item = new DeliveryOperationsViewDTO(
            id: 1,
            eventId: 'evt-1',
            channel: 'chan-1',
            operationType: 'op-1',
            actorType: 'act-1',
            actorId: 42,
            targetType: 'tar-1',
            targetId: 43,
            status: 'stat-1',
            attemptNo: 0,
            scheduledAt: null,
            completedAt: null,
            correlationId: null,
            requestId: null,
            provider: null,
            providerMessageId: null,
            errorCode: null,
            errorMessage: null,
            metadata: null,
            occurredAt: new DateTimeImmutable('2023-01-01')
        );

        $dto = new DeliveryOperationsAdminPageResultDTO(
            items: [$item],
            page: 1,
            perPage: 20,
            total: 100,
            filtered: 50,
            totalPages: 3,
            hasNext: true,
            hasPrevious: false,
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        );

        $this->assertCount(1, $dto);
        foreach ($dto as $i) {
            $this->assertSame($item, $i);
        }

        $json = $dto->jsonSerialize();
        $this->assertEquals([
            'items' => [$item],
            'page' => 1,
            'perPage' => 20,
            'total' => 100,
            'filtered' => 50,
            'totalPages' => 3,
            'hasNext' => true,
            'hasPrevious' => false,
            'sortBy' => 'occurred_at',
            'sortDirection' => 'DESC',
        ], $json);

        // Exact key ordering test
        $keys = array_keys($json);
        $this->assertEquals([
            'items',
            'page',
            'perPage',
            'total',
            'filtered',
            'totalPages',
            'hasNext',
            'hasPrevious',
            'sortBy',
            'sortDirection'
        ], $keys);
    }
}
