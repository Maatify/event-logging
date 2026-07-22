<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminPageResultDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminPageResultDTO
 */
final class DiagnosticsTelemetryAdminPageResultDTOTest extends TestCase
{
    public function testItPreservesPropertiesAndSerializesCorrectly(): void
    {
        $item1 = new DiagnosticsTelemetryEventDTO(
            id: 10,
            eventId: 'event-10',
            eventKey: 'event_1',
            severity: new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface {
                public function value(): string { return 'INFO'; }
            },
            context: new \Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO(
                actorType: new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface {
                    public function value(): string { return 'admin'; }
                },
                actorId: 99,
                correlationId: 'req-1',
                requestId: 'r-1',
                routeName: 'route_1',
                ipAddress: '127.0.0.1',
                userAgent: 'test-agent',
                occurredAt: new DateTimeImmutable('2023-01-01T12:00:00Z')
            ),
            durationMs: 100,
            metadata: ['foo' => 'bar']
        );

        $item2 = new DiagnosticsTelemetryEventDTO(
            id: 11,
            eventId: 'event-11',
            eventKey: 'event_2',
            severity: new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface {
                public function value(): string { return 'ERROR'; }
            },
            context: new \Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO(
                actorType: new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface {
                    public function value(): string { return 'system'; }
                },
                actorId: 1,
                correlationId: 'req-2',
                requestId: 'r-2',
                routeName: 'route_2',
                ipAddress: '192.168.1.1',
                userAgent: 'system-agent',
                occurredAt: new DateTimeImmutable('2023-01-01T13:00:00Z')
            ),
            durationMs: null,
            metadata: null
        );

        $items = [$item1, $item2];

        $dto = new DiagnosticsTelemetryAdminPageResultDTO(
            items: $items,
            page: 2,
            perPage: 50,
            total: 100,
            filtered: 50,
            totalPages: 1,
            hasNext: false,
            hasPrevious: true,
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        );

        $this->assertSame($items, $dto->items);
        $this->assertSame(2, $dto->page);
        $this->assertSame(50, $dto->perPage);
        $this->assertSame(100, $dto->total);
        $this->assertSame(50, $dto->filtered);
        $this->assertSame(1, $dto->totalPages);
        $this->assertFalse($dto->hasNext);
        $this->assertTrue($dto->hasPrevious);
        $this->assertSame('occurred_at', $dto->sortBy);
        $this->assertSame('DESC', $dto->sortDirection);

        $iteratorItems = iterator_to_array($dto);
        $this->assertSame($items, $iteratorItems);
        $this->assertSame($item1, $iteratorItems[0]);
        $this->assertSame($item2, $iteratorItems[1]);

        $serialized = $dto->jsonSerialize();
        $expectedKeys = [
            'items',
            'page',
            'perPage',
            'total',
            'filtered',
            'totalPages',
            'hasNext',
            'hasPrevious',
            'sortBy',
            'sortDirection',
        ];

        $this->assertSame($expectedKeys, array_keys($serialized));
        $this->assertSame($items, $serialized['items']);
        $this->assertSame(2, $serialized['page']);
        $this->assertSame(50, $serialized['perPage']);
        $this->assertSame(100, $serialized['total']);
        $this->assertSame(50, $serialized['filtered']);
        $this->assertSame(1, $serialized['totalPages']);
        $this->assertFalse($serialized['hasNext']);
        $this->assertTrue($serialized['hasPrevious']);
        $this->assertSame('occurred_at', $serialized['sortBy']);
        $this->assertSame('DESC', $serialized['sortDirection']);
    }
}
