<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditViewDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new AuthoritativeAuditViewDTO(
            1,
            'evt-1',
            'ADMIN',
            123,
            'CREATE',
            'USER',
            456,
            '127.0.0.1',
            'Mozilla',
            'corr-1',
            ['foo' => 'bar'],
            $date
        );

        $expected = [
            'id' => 1,
            'eventId' => 'evt-1',
            'actorType' => 'ADMIN',
            'actorId' => 123,
            'action' => 'CREATE',
            'targetType' => 'USER',
            'targetId' => 456,
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Mozilla',
            'correlationId' => 'corr-1',
            'changes' => ['foo' => 'bar'],
            'occurredAt' => $date->format(DATE_ATOM),
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new AuthoritativeAuditViewDTO(
            1,
            'evt-1',
            null,
            null,
            'CREATE',
            null,
            null,
            null,
            null,
            null,
            null,
            $date
        );

        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertNull($dto->ipAddress);
        $this->assertNull($dto->userAgent);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->changes);
    }
}
