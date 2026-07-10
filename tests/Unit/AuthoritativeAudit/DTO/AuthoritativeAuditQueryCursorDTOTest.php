<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryCursorDTO;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditQueryCursorDTOTest extends TestCase
{
    public function testJsonSerializeReturnsCorrectFormat(): void
    {
        $date = new DateTimeImmutable('2023-10-15T10:00:00+00:00');
        $dto = new AuthoritativeAuditQueryCursorDTO(
            occurredAt: $date,
            id: 123
        );

        $this->assertSame([
            'occurredAt' => $date->format(DATE_ATOM),
            'id' => 123,
        ], $dto->jsonSerialize());
    }
}
