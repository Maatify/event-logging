<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryCursorDTO;
use PHPUnit\Framework\TestCase;

class AuditTrailQueryCursorDTOTest extends TestCase
{
    public function testJsonSerializeReturnsCorrectFormat(): void
    {
        $date = new DateTimeImmutable('2023-10-15T10:00:00+00:00');
        $dto = new AuditTrailQueryCursorDTO(
            occurredAt: $date,
            id: 123
        );

        $expected = [
            'occurredAt' => $date->format(DATE_ATOM),
            'id' => 123,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
