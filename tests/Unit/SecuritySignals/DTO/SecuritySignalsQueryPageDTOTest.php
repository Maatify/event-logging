<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryCursorDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryPageDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use PHPUnit\Framework\TestCase;

class SecuritySignalsQueryPageDTOTest extends TestCase
{
    public function testItSerializesProperly(): void
    {
        $cursor = new SecuritySignalsQueryCursorDTO(
            new DateTimeImmutable('2023-10-15T10:00:00+00:00'),
            123
        );

        $view = $this->createView(123);

        $dto = new SecuritySignalsQueryPageDTO(
            items: [$view],
            nextCursor: $cursor,
            hasMore: true
        );

        $expected = [
            'items' => [$view],
            'nextCursor' => $cursor,
            'hasMore' => true,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testItIsIterable(): void
    {
        $view = $this->createView(123);
        $dto = new SecuritySignalsQueryPageDTO([$view, $view], null, false);

        $count = 0;
        foreach ($dto as $item) {
            $this->assertSame($view, $item);
            $count++;
        }

        $this->assertSame(2, $count);
    }

    private function createView(int $id): SecuritySignalsViewDTO
    {
        return new SecuritySignalsViewDTO(
            id: $id,
            eventId: 'ev-'.$id,
            actorType: 'user',
            actorId: 1,
            signalType: 'login.failed',
            severity: 'medium',
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            metadata: null,
            occurredAt: new DateTimeImmutable('2023-10-15T10:00:00+00:00')
        );
    }
}
