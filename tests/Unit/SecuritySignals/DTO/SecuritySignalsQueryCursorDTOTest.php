<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryCursorDTO;
use PHPUnit\Framework\TestCase;

class SecuritySignalsQueryCursorDTOTest extends TestCase
{
    public function testJsonSerializeReturnsCorrectFormat(): void
    {
        $date = new DateTimeImmutable('2023-10-15T10:00:00+00:00');
        $dto = new SecuritySignalsQueryCursorDTO(
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
