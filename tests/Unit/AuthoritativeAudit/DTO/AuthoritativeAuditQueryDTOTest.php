<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditQueryDTOTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $dto = new AuthoritativeAuditQueryDTO();

        $this->assertNull($dto->after);
        $this->assertNull($dto->before);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertNull($dto->action);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->cursorOccurredAt);
        $this->assertNull($dto->cursorId);
        $this->assertSame(50, $dto->limit);

        $expected = [
            'after' => null,
            'before' => null,
            'actorType' => null,
            'actorId' => null,
            'targetType' => null,
            'targetId' => null,
            'action' => null,
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
        $before = new DateTimeImmutable('2023-01-31T23:59:59+00:00');
        $cursorOccurredAt = new DateTimeImmutable('2023-01-15T12:00:00+00:00');

        $dto = new AuthoritativeAuditQueryDTO(
            after: $after,
            before: $before,
            actorType: 'ADMIN',
            actorId: 1,
            targetType: 'USER',
            targetId: 2,
            action: 'CREATE',
            correlationId: 'corr-1',
            cursorOccurredAt: $cursorOccurredAt,
            cursorId: 99,
            limit: 100
        );

        $expected = [
            'after' => $after->format(DATE_ATOM),
            'before' => $before->format(DATE_ATOM),
            'actorType' => 'ADMIN',
            'actorId' => 1,
            'targetType' => 'USER',
            'targetId' => 2,
            'action' => 'CREATE',
            'correlationId' => 'corr-1',
            'cursorOccurredAt' => $cursorOccurredAt->format(DATE_ATOM),
            'cursorId' => 99,
            'limit' => 100,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
