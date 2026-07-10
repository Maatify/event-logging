<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeEnum;
use PHPUnit\Framework\TestCase;

class BehaviorTraceEventDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $context = new BehaviorTraceContextDTO(
            BehaviorTraceActorTypeEnum::USER,
            123,
            'corr',
            'req',
            'route',
            '127.0.0.1',
            'Mozilla',
            $date
        );

        $dto = new BehaviorTraceEventDTO(
            1,
            'evt-1',
            'CLICK',
            'BUTTON',
            456,
            $context,
            ['foo' => 'bar']
        );

        $expected = [
            'id' => 1,
            'eventId' => 'evt-1',
            'action' => 'CLICK',
            'entityType' => 'BUTTON',
            'entityId' => 456,
            'context' => $context->jsonSerialize(),
            'metadata' => ['foo' => 'bar'],
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $context = new BehaviorTraceContextDTO(
            BehaviorTraceActorTypeEnum::ANONYMOUS,
            null,
            null,
            null,
            null,
            null,
            null,
            $date
        );

        $dto = new BehaviorTraceEventDTO(
            1,
            'evt-1',
            'VIEW',
            null,
            null,
            $context,
            null
        );

        $this->assertNull($dto->entityType);
        $this->assertNull($dto->entityId);
        $this->assertNull($dto->metadata);
    }
}
