<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use PHPUnit\Framework\TestCase;

class BehaviorTraceQueryDTOTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $dto = new BehaviorTraceQueryDTO();

        $this->assertNull($dto->actorType);
        $this->assertSame(50, $dto->limit);

        $expected = [
            'after' => null,
            'before' => null,
            'actorType' => null,
            'actorId' => null,
            'entityType' => null,
            'entityId' => null,
            'action' => null,
            'requestId' => null,
            'correlationId' => null,
            'cursorOccurredAt' => null,
            'cursorId' => null,
            'limit' => 50,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testFullyPopulated(): void
    {
        $after = new DateTimeImmutable('2023-01-01T00:00:00+00:00');
        $dto = new BehaviorTraceQueryDTO(
            after: $after,
            actorType: 'USER',
            actorId: 1,
            entityType: 'BUTTON',
            entityId: 2,
            action: 'CLICK',
            requestId: 'req',
            correlationId: 'corr',
            limit: 100
        );

        $expected = [
            'after' => $after->format(DATE_ATOM),
            'before' => null,
            'actorType' => 'USER',
            'actorId' => 1,
            'entityType' => 'BUTTON',
            'entityId' => 2,
            'action' => 'CLICK',
            'requestId' => 'req',
            'correlationId' => 'corr',
            'cursorOccurredAt' => null,
            'cursorId' => null,
            'limit' => 100,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
