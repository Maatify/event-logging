<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminPageResultDTOTest extends TestCase
{
    public function testConstructAndSerialize(): void
    {
        $occurredAt = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $item = new AuthoritativeAuditViewDTO(
            id: 1,
            eventId: 'event-1',
            actorType: 'user',
            actorId: 1,
            action: 'create',
            targetType: 'resource',
            targetId: 1,
            ipAddress: '127.0.0.1',
            userAgent: 'test-agent',
            correlationId: 'corr-1',
            changes: ['key' => 'value'],
            occurredAt: $occurredAt
        );

        $dto = new AuthoritativeAuditAdminPageResultDTO(
            items: [$item],
            page: 1,
            perPage: 20,
            total: 100,
            filtered: 1,
            totalPages: 5,
            hasNext: true,
            hasPrevious: false,
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        );

        $this->assertCount(1, $dto);
        $iterator = $dto->getIterator();
        $this->assertCount(1, $iterator);

        $json = $dto->jsonSerialize();
        $this->assertSame(1, $json['page']);
        $this->assertSame(20, $json['perPage']);
        $this->assertSame(100, $json['total']);
        $this->assertSame(1, $json['filtered']);
        $this->assertSame(5, $json['totalPages']);
        $this->assertTrue($json['hasNext']);
        $this->assertFalse($json['hasPrevious']);
        $this->assertSame('occurred_at', $json['sortBy']);
        $this->assertSame('DESC', $json['sortDirection']);
        $this->assertCount(1, $json['items']);
    }
}
