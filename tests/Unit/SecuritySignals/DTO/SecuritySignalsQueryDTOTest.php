<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use PHPUnit\Framework\TestCase;

class SecuritySignalsQueryDTOTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $dto = new SecuritySignalsQueryDTO();

        $this->assertNull($dto->actorType);
        $this->assertSame(50, $dto->limit);

        $expected = [
            'after' => null,
            'before' => null,
            'actorType' => null,
            'actorId' => null,
            'signalType' => null,
            'severity' => null,
            'requestId' => null,
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
        $dto = new SecuritySignalsQueryDTO(
            after: $after,
            actorType: 'ADMIN',
            actorId: 1,
            signalType: 'LOGIN',
            severity: 'HIGH',
            requestId: 'req',
            correlationId: 'corr',
            limit: 100
        );

        $expected = [
            'after' => $after->format(DATE_ATOM),
            'before' => null,
            'actorType' => 'ADMIN',
            'actorId' => 1,
            'signalType' => 'LOGIN',
            'severity' => 'HIGH',
            'requestId' => 'req',
            'correlationId' => 'corr',
            'cursorOccurredAt' => null,
            'cursorId' => null,
            'limit' => 100,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
