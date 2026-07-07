<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;
use PHPUnit\Framework\TestCase;

class AuditTrailViewDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new AuditTrailViewDTO(
            1,
            'evt-1',
            'ADMIN',
            123,
            'CREATE',
            'USER',
            456,
            'PROFILE',
            789,
            'route',
            '/path',
            'host',
            'corr',
            'req',
            'rname',
            'ip',
            'ua',
            ['k' => 'v'],
            $date
        );

        $expected = [
            'id' => 1,
            'eventId' => 'evt-1',
            'actorType' => 'ADMIN',
            'actorId' => 123,
            'eventKey' => 'CREATE',
            'entityType' => 'USER',
            'entityId' => 456,
            'subjectType' => 'PROFILE',
            'subjectId' => 789,
            'referrerRouteName' => 'route',
            'referrerPath' => '/path',
            'referrerHost' => 'host',
            'correlationId' => 'corr',
            'requestId' => 'req',
            'routeName' => 'rname',
            'ipAddress' => 'ip',
            'userAgent' => 'ua',
            'metadata' => ['k' => 'v'],
            'occurredAt' => $date->format(DATE_ATOM),
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new AuditTrailViewDTO(
            1,
            'evt-1',
            'ADMIN',
            null,
            'CREATE',
            'USER',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $date
        );

        $this->assertNull($dto->actorId);
        $this->assertNull($dto->entityId);
        $this->assertNull($dto->subjectType);
        $this->assertNull($dto->metadata);
    }
}
