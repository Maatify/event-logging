<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminQueryRequestDTOTest extends TestCase
{
    public function testConstructValidValues(): void
    {
        $after = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $before = new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC'));

        $dto = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: 'event-123',
            actorType: 'user',
            actorId: 42,
            targetType: 'resource',
            targetId: 100,
            action: 'create',
            correlationId: 'corr-456',
            after: $after,
            before: $before,
            page: 1,
            perPage: 20,
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        );

        $this->assertSame('event-123', $dto->eventId);
        $this->assertSame('user', $dto->actorType);
        $this->assertSame(42, $dto->actorId);
        $this->assertSame('resource', $dto->targetType);
        $this->assertSame(100, $dto->targetId);
        $this->assertSame('create', $dto->action);
        $this->assertSame('corr-456', $dto->correlationId);
        $this->assertSame($after, $dto->after);
        $this->assertSame($before, $dto->before);
        $this->assertSame(1, $dto->page);
        $this->assertSame(20, $dto->perPage);
        $this->assertSame('occurred_at', $dto->sortBy);
        $this->assertSame('DESC', $dto->sortDirection);
    }

    public function testInvalidActorIdThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query ID: actorId');
        new AuthoritativeAuditAdminQueryRequestDTO(actorId: 0);
    }

    public function testInvalidTargetIdThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query ID: targetId');
        new AuthoritativeAuditAdminQueryRequestDTO(targetId: -1);
    }

    public function testInvalidDateRangeThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before');
        new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-02 00:00:00'),
            before: new DateTimeImmutable('2024-01-01 00:00:00')
        );
    }

    public function testInvalidEventIdLengthThrows(): void
    {
        $this->expectException(AuthoritativeAuditAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuthoritativeAudit Admin Query length: eventId');
        new AuthoritativeAuditAdminQueryRequestDTO(eventId: str_repeat('a', 37));
    }
}
