<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsQueryDTO;
use PHPUnit\Framework\TestCase;

class DeliveryOperationsQueryDTOTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $dto = new DeliveryOperationsQueryDTO();

        $this->assertNull($dto->actorType);
        $this->assertSame(50, $dto->limit);

        $expected = [
            'after' => null,
            'before' => null,
            'actorType' => null,
            'actorId' => null,
            'targetType' => null,
            'targetId' => null,
            'channel' => null,
            'operationType' => null,
            'status' => null,
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
        $dto = new DeliveryOperationsQueryDTO(
            after: $after,
            actorType: 'SYSTEM',
            actorId: 1,
            targetType: 'USER',
            targetId: 2,
            channel: 'EMAIL',
            operationType: 'SEND',
            status: 'FAILED',
            requestId: 'req',
            correlationId: 'corr',
            limit: 100
        );

        $expected = [
            'after' => $after->format(DATE_ATOM),
            'before' => null,
            'actorType' => 'SYSTEM',
            'actorId' => 1,
            'targetType' => 'USER',
            'targetId' => 2,
            'channel' => 'EMAIL',
            'operationType' => 'SEND',
            'status' => 'FAILED',
            'requestId' => 'req',
            'correlationId' => 'corr',
            'cursorOccurredAt' => null,
            'cursorId' => null,
            'limit' => 100,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
