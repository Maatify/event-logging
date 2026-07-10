<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryCursorDTO;
use PHPUnit\Framework\TestCase;

class BehaviorTraceQueryCursorDTOTest extends TestCase
{
    public function testItSerializesProperly(): void
    {
        $date = new DateTimeImmutable('2023-10-15T10:00:00+00:00');
        $dto = new BehaviorTraceQueryCursorDTO($date, 123);

        $this->assertSame([
            'occurredAt' => '2023-10-15T10:00:00+00:00',
            'id' => 123,
        ], $dto->jsonSerialize());
    }
}
