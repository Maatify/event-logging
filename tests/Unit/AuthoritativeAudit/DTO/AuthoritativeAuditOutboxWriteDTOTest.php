<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditOutboxWriteDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new AuthoritativeAuditOutboxWriteDTO(
            'evt-1',
            'ADMIN',
            123,
            'CREATE',
            'USER',
            456,
            'HIGH',
            ['key' => 'value'],
            'corr-1',
            $date
        );

        $expected = [
            'eventId' => 'evt-1',
            'actorType' => 'ADMIN',
            'actorId' => 123,
            'action' => 'CREATE',
            'targetType' => 'USER',
            'targetId' => 456,
            'riskLevel' => 'HIGH',
            'payload' => ['key' => 'value'],
            'correlationId' => 'corr-1',
            'createdAt' => $date->format(DATE_ATOM),
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new AuthoritativeAuditOutboxWriteDTO(
            'evt-1',
            'SYSTEM',
            null,
            'LOGIN',
            'APP',
            null,
            'LOW',
            [],
            'corr-1',
            $date
        );

        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetId);
    }
}
