<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use PHPUnit\Framework\TestCase;

class AuditTrailQueryDTOTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $dto = new AuditTrailQueryDTO();

        $this->assertNull($dto->actorType);
        $this->assertSame(50, $dto->limit);

        $expected = [
            'actorType' => null,
            'actorId' => null,
            'eventKey' => null,
            'entityType' => null,
            'entityId' => null,
            'subjectType' => null,
            'subjectId' => null,
            'requestId' => null,
            'correlationId' => null,
            'after' => null,
            'before' => null,
            'cursorOccurredAt' => null,
            'cursorId' => null,
            'limit' => 50,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testFullyPopulated(): void
    {
        $after = new DateTimeImmutable('2023-01-01T00:00:00+00:00');
        $dto = new AuditTrailQueryDTO(
            actorType: 'ADMIN',
            actorId: 1,
            eventKey: 'CREATE',
            entityType: 'USER',
            entityId: 2,
            subjectType: 'PROFILE',
            subjectId: 3,
            requestId: 'req-1',
            correlationId: 'corr-1',
            after: $after,
            limit: 100
        );

        $expected = [
            'actorType' => 'ADMIN',
            'actorId' => 1,
            'eventKey' => 'CREATE',
            'entityType' => 'USER',
            'entityId' => 2,
            'subjectType' => 'PROFILE',
            'subjectId' => 3,
            'requestId' => 'req-1',
            'correlationId' => 'corr-1',
            'after' => $after->format(DATE_ATOM),
            'before' => null,
            'cursorOccurredAt' => null,
            'cursorId' => null,
            'limit' => 100,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
