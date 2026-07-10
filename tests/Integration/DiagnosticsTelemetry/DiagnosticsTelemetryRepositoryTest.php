<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\DiagnosticsTelemetry;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryQueryDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryLoggerMysqlRepository;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryLoggerMysqlRepository
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryQueryMysqlRepository
 */
final class DiagnosticsTelemetryRepositoryTest extends MysqlIntegrationTestCase
{
    private DiagnosticsTelemetryLoggerMysqlRepository $logger;
    private DiagnosticsTelemetryQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo !== null) {
            $this->logger = new DiagnosticsTelemetryLoggerMysqlRepository($this->pdo);
            $this->query = new DiagnosticsTelemetryQueryMysqlRepository($this->pdo);
        }
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql';
    }

    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_diagnostics_telemetry',
        ];
    }

    public function testWriteAndQueryRoundtrip(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $actorType = new class implements DiagnosticsTelemetryActorTypeInterface {
            public function value(): string { return 'SYSTEM'; }
        };

        $context = new DiagnosticsTelemetryContextDTO(
            actorType: $actorType,
            actorId: 2,
            correlationId: 'corr-diag',
            requestId: 'req-diag',
            routeName: 'api_status',
            ipAddress: '10.0.0.1',
            userAgent: 'Curl',
            occurredAt: $now
        );

        $severity = new class implements DiagnosticsTelemetrySeverityInterface {
            public function value(): string { return 'INFO'; }
        };

        $recordDto = new DiagnosticsTelemetryEventDTO(
            id: 0,
            eventId: 'diag-event-1',
            eventKey: 'http.request',
            severity: $severity,
            context: $context,
            durationMs: 150,
            metadata: ['status' => 200]
        );

        $this->logger->write($recordDto);

        $queryDto = new DiagnosticsTelemetryQueryDTO(
            eventKey: 'http.request',
            actorType: 'SYSTEM',
            severity: 'INFO'
        );

        $results = $this->query->find($queryDto);
        $this->assertCount(1, $results);

        $viewDto = $results[0];
        $this->assertGreaterThan(0, $viewDto->id);
        $this->assertSame('diag-event-1', $viewDto->eventId);
        $this->assertSame('http.request', $viewDto->eventKey);
        $this->assertSame('INFO', $viewDto->severity->value());
        $this->assertSame('SYSTEM', $viewDto->context->actorType->value());
        $this->assertSame(2, $viewDto->context->actorId);
        $this->assertSame('corr-diag', $viewDto->context->correlationId);
        $this->assertSame(150, $viewDto->durationMs);
        $this->assertSame(['status' => 200], $viewDto->metadata);
        $this->assertEquals($now, $viewDto->context->occurredAt);
    }

    public function testCursorPagination(): void
    {
        $now1 = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $now2 = new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC'));

        $actorType = new class implements DiagnosticsTelemetryActorTypeInterface {
            public function value(): string { return 'SYSTEM'; }
        };

        $severity = new class implements DiagnosticsTelemetrySeverityInterface {
            public function value(): string { return 'INFO'; }
        };

        $c1 = new DiagnosticsTelemetryContextDTO($actorType, 1, null, null, null, null, null, $now1);
        $c2 = new DiagnosticsTelemetryContextDTO($actorType, 1, null, null, null, null, null, $now2);

        $dto1 = new DiagnosticsTelemetryEventDTO(0, 'evt-1', 'sys.act', $severity, $c1, null, []);
        $dto2 = new DiagnosticsTelemetryEventDTO(0, 'evt-2', 'sys.act', $severity, $c2, null, []);
        $dto3 = new DiagnosticsTelemetryEventDTO(0, 'evt-3', 'sys.act', $severity, $c2, null, []);

        $this->logger->write($dto1);
        $this->logger->write($dto2);
        $this->logger->write($dto3);

        $query1 = new DiagnosticsTelemetryQueryDTO(limit: 1);
        $res1 = $this->query->find($query1);
        $this->assertCount(1, $res1);
        $this->assertSame('evt-3', $res1[0]->eventId);

        $stmt = $this->pdo->query("SELECT id FROM maa_event_logging_diagnostics_telemetry WHERE event_id = 'evt-3'");
        $evt3Id = (int)$stmt->fetchColumn();

        $query2 = new DiagnosticsTelemetryQueryDTO(
            cursorOccurredAt: $res1[0]->context->occurredAt,
            cursorId: $evt3Id,
            limit: 1
        );
        $res2 = $this->query->find($query2);
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        $stmt = $this->pdo->query("SELECT id FROM maa_event_logging_diagnostics_telemetry WHERE event_id = 'evt-2'");
        $evt2Id = (int)$stmt->fetchColumn();

        $query3 = new DiagnosticsTelemetryQueryDTO(
            cursorOccurredAt: $res2[0]->context->occurredAt,
            cursorId: $evt2Id,
            limit: 1
        );
        $res3 = $this->query->find($query3);
        $this->assertCount(1, $res3);
        $this->assertSame('evt-1', $res3[0]->eventId);
    }
}
