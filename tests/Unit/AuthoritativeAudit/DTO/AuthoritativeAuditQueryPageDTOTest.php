<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryCursorDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryPageDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditQueryPageDTOTest extends TestCase
{
    public function testItSerializesProperly(): void
    {
        $cursor = new AuthoritativeAuditQueryCursorDTO(new DateTimeImmutable('2023-10-15T10:00:00+00:00'), 123);
        $view = $this->createDummyItem(123);
        $dto = new AuthoritativeAuditQueryPageDTO(items: [$view], nextCursor: $cursor, hasMore: true);

        $this->assertSame([
            'items' => [$view],
            'nextCursor' => $cursor,
            'hasMore' => true,
        ], $dto->jsonSerialize());
    }

    public function testItIsIterable(): void
    {
        $view = $this->createDummyItem(123);
        $dto = new AuthoritativeAuditQueryPageDTO([$view, $view], null, false);

        $count = 0;
        foreach ($dto as $item) {
            $this->assertSame($view, $item);
            $count++;
        }

        $this->assertSame(2, $count);
    }

    private function createDummyItem(int $id): AuthoritativeAuditViewDTO
    {
        return new AuthoritativeAuditViewDTO(
            id: $id,
            eventId: 'ev-'.$id,
            actorType: 'user',
            actorId: 1,
            action: 'created',
            targetType: 'post',
            targetId: 1,
            ipAddress: null,
            userAgent: null,
            correlationId: null,
            changes: null,
            occurredAt: new DateTimeImmutable('2023-10-15T10:00:00+00:00')
        );
    }
}
