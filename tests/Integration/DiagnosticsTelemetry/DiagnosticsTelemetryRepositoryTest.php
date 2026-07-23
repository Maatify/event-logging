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
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryLoggerMysqlRepository
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryQueryMysqlRepository
 */
final class DiagnosticsTelemetryRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private DiagnosticsTelemetryLoggerMysqlRepository $logger;
    private DiagnosticsTelemetryQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new \RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for DiagnosticsTelemetry primitive integration tests.');
        }

        $this->pdo = new \PDO(
            $dsn,
            getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root',
            getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        $this->resetSchema();
        $this->logger = new DiagnosticsTelemetryLoggerMysqlRepository($this->pdo);
        $this->query = new DiagnosticsTelemetryQueryMysqlRepository($this->pdo);
    }

    private function resetSchema(): void
    {
        $schema = file_get_contents(__DIR__ . '/../../../src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql');
        if (! is_string($schema) || $schema === '') {
            throw new \RuntimeException('Failed to read DiagnosticsTelemetry schema.');
        }

        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_diagnostics_telemetry;');
        $this->pdo->exec($schema);
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
        if ($stmt === false) {
            $this->fail('Failed to execute PDO query.');
        }
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
        if ($stmt === false) {
            $this->fail('Failed to execute PDO query.');
        }
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

    public function testLegacyReadPaginationMaintainsAscendingOrder(): void
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

        $res1 = iterator_to_array($this->query->read(null, 1));
        $this->assertCount(1, $res1);
        $this->assertSame('evt-1', $res1[0]->eventId);

        $stmt = $this->pdo->query("SELECT id FROM maa_event_logging_diagnostics_telemetry WHERE event_id = 'evt-1'");
        if ($stmt === false) {
            $this->fail('Failed to execute PDO query.');
        }
        $evt1Id = (int)$stmt->fetchColumn();

        $cursor1 = new \Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryCursorDTO(
            lastOccurredAt: $res1[0]->context->occurredAt,
            lastId: $evt1Id
        );
        $res2 = iterator_to_array($this->query->read($cursor1, 1));
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        $stmt2 = $this->pdo->query("SELECT id FROM maa_event_logging_diagnostics_telemetry WHERE event_id = 'evt-2'");
        if ($stmt2 === false) {
            $this->fail('Failed to execute PDO query.');
        }
        $evt2Id = (int)$stmt2->fetchColumn();

        $cursor2 = new \Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryCursorDTO(
            lastOccurredAt: $res2[0]->context->occurredAt,
            lastId: $evt2Id
        );
        $res3 = iterator_to_array($this->query->read($cursor2, 1));
        $this->assertCount(1, $res3);
        $this->assertSame('evt-3', $res3[0]->eventId);
    }

    public function testItPreservesCallerOwnedTransaction(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $actorType = new class implements DiagnosticsTelemetryActorTypeInterface {
            public function value(): string { return 'SYSTEM'; }
        };
        $severity = new class implements DiagnosticsTelemetrySeverityInterface {
            public function value(): string { return 'INFO'; }
        };

        $context = new DiagnosticsTelemetryContextDTO($actorType, 1, null, null, null, null, null, $now);
        $dto = new DiagnosticsTelemetryEventDTO(0, 'tx-evt', 'sys.tx', $severity, $context, null, []);

        $this->pdo->beginTransaction();

        $this->logger->write($dto);

        $query = new DiagnosticsTelemetryQueryDTO(eventKey: 'sys.tx');
        $res1 = $this->query->find($query);
        $this->assertCount(1, $res1);
        $this->assertSame('tx-evt', $res1[0]->eventId);

        $this->pdo->rollBack();

        $res2 = $this->query->find($query);
        $this->assertCount(0, $res2);
    }

    public function testItHydratesViaCustomPolicy(): void
    {
        $customPolicy = new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface {
            public function normalizeSeverity(string|\Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface $severity): \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface
            {
                return new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface {
                    public function value(): string { return 'CUSTOM_SEVERITY'; }
                };
            }

            public function normalizeActorType(string|\Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface $actorType): \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface
            {
                return new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface {
                    public function value(): string { return 'CUSTOM_ACTOR'; }
                };
            }

            public function validateMetadataSize(string $json): bool
            {
                return true;
            }
        };

        $customQueryRepo = new DiagnosticsTelemetryQueryMysqlRepository($this->pdo, $customPolicy);

        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $actorType = new class implements DiagnosticsTelemetryActorTypeInterface {
            public function value(): string { return 'SYSTEM'; }
        };
        $severity = new class implements DiagnosticsTelemetrySeverityInterface {
            public function value(): string { return 'INFO'; }
        };

        $context = new DiagnosticsTelemetryContextDTO($actorType, 1, null, null, null, null, null, $now);
        $dto = new DiagnosticsTelemetryEventDTO(0, 'policy-evt', 'sys.policy', $severity, $context, null, []);

        $this->logger->write($dto);

        $query = new DiagnosticsTelemetryQueryDTO(eventKey: 'sys.policy');
        $res = $customQueryRepo->find($query);

        $this->assertCount(1, $res);
        $this->assertSame('policy-evt', $res[0]->eventId);

        // The raw DB has 'SYSTEM'/'INFO', but hydration via custom policy rewrites them.
        $this->assertSame('CUSTOM_SEVERITY', $res[0]->severity->value());
        $this->assertSame('CUSTOM_ACTOR', $res[0]->context->actorType->value());
    }
}