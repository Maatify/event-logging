<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryCursorDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryPageDTO;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeEnum;
use PHPUnit\Framework\TestCase;

class BehaviorTraceQueryPageDTOTest extends TestCase
{
    private function createItem(): BehaviorTraceEventDTO
    {
        return new BehaviorTraceEventDTO(
            id: 123,
            eventId: 'ev-123',
            action: 'clicked',
            entityType: 'button',
            entityId: 1,
            context: new BehaviorTraceContextDTO(
                BehaviorTraceActorTypeEnum::USER,
                1,
                null,
                null,
                null,
                null,
                null,
                new DateTimeImmutable('2023-10-15T10:00:00+00:00')
            ),
            metadata: null
        );
    }

    public function testItSerializesProperly(): void
    {
        $cursor = new BehaviorTraceQueryCursorDTO(
            new DateTimeImmutable('2023-10-15T10:00:00+00:00'),
            123
        );
        $item = $this->createItem();
        $dto = new BehaviorTraceQueryPageDTO([$item], $cursor, true);

        $this->assertSame([
            'items' => [$item],
            'nextCursor' => $cursor,
            'hasMore' => true,
        ], $dto->jsonSerialize());
    }

    public function testItIsIterable(): void
    {
        $item = $this->createItem();
        $dto = new BehaviorTraceQueryPageDTO([$item, $item], null, false);

        $count = 0;
        foreach ($dto as $iterated) {
            $this->assertSame($item, $iterated);
            $count++;
        }

        $this->assertSame(2, $count);
    }
}
