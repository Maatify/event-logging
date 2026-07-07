<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceCursorDTO;
use PHPUnit\Framework\TestCase;

class BehaviorTraceCursorDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
        $dto = new BehaviorTraceCursorDTO($date, 42);

        $expected = [
            'lastOccurredAt' => $date->format(DATE_ATOM),
            'lastId' => 42,
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }
}
