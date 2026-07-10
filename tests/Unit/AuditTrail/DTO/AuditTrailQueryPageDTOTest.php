<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryCursorDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryPageDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;
use PHPUnit\Framework\TestCase;

class AuditTrailQueryPageDTOTest extends TestCase
{
    public function testItSerializesProperly(): void
    {
        $cursor = new AuditTrailQueryCursorDTO(
            new DateTimeImmutable('2023-10-15T10:00:00+00:00'),
            123
        );

        $view = new AuditTrailViewDTO(
            id: 123,
            eventId: 'ev-123',
            actorType: 'user',
            actorId: 1,
            eventKey: 'created',
            entityType: 'post',
            entityId: 1,
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
            occurredAt: new DateTimeImmutable('2023-10-15T10:00:00+00:00')
        );

        $dto = new AuditTrailQueryPageDTO(
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
        $view = new AuditTrailViewDTO(
            id: 123,
            eventId: 'ev-123',
            actorType: 'user',
            actorId: 1,
            eventKey: 'created',
            entityType: 'post',
            entityId: 1,
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
            occurredAt: new DateTimeImmutable('2023-10-15T10:00:00+00:00')
        );
        $dto = new AuditTrailQueryPageDTO([$view, $view], null, false);

        $count = 0;
        foreach ($dto as $item) {
            $this->assertSame($view, $item);
            $count++;
        }

        $this->assertSame(2, $count);
    }
}
