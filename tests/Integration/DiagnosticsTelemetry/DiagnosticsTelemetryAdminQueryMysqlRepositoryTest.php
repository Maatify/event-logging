<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\DiagnosticsTelemetry;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryAdminQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryAdminQueryMysqlRepository
 */
final class DiagnosticsTelemetryAdminQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private DiagnosticsTelemetryAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for DiagnosticsTelemetry integration tests.');
        }

        $this->pdo = new PDO(
            $dsn,
            getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root',
            getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        $this->resetSchema();
        $this->repository = new DiagnosticsTelemetryAdminQueryMysqlRepository($this->pdo);
    }

    private function resetSchema(): void
    {
        $schema = file_get_contents(__DIR__ . '/../../../src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql');
        if (! is_string($schema) || $schema === '') {
            throw new RuntimeException('Failed to read DiagnosticsTelemetry schema.');
        }

        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_diagnostics_telemetry;');
        $this->pdo->exec($schema);
    }

    private function insertLog(
        string $eventId,
        string $eventKey = 'login',
        string $severity = 'INFO',
        string $actorType = 'User',
        int $actorId = 123,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $routeName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $durationMs = null,
        ?string $metadata = null,
        string $occurredAt = '2025-01-01 10:00:00.000000'
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_diagnostics_telemetry
            (event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId,
            $eventKey,
            $severity,
            $actorType,
            $actorId,
            $correlationId,
            $requestId,
            $routeName,
            $ipAddress,
            $userAgent,
            $durationMs,
            $metadata,
            $occurredAt
        ]);
    }

    public function testItReturnsEmptyResultForEmptyTable(): void
    {
        $request = new DiagnosticsTelemetryAdminQueryRequestDTO();
        $result = $this->repository->paginate($request);

        $this->assertSame([], $result->items);
        $this->assertSame(0, $result->total);
        $this->assertSame(0, $result->filtered);
        $this->assertSame(0, $result->totalPages);
    }

    public function testItPaginatesWithoutFilters(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->insertLog(eventId: "evt-{$i}", occurredAt: "2025-01-01 10:00:{$i}.000000");
        }

        $request = new DiagnosticsTelemetryAdminQueryRequestDTO(page: 2, perPage: 10);
        $result = $this->repository->paginate($request);

        $this->assertSame(25, $result->total);
        $this->assertSame(25, $result->filtered);
        $this->assertSame(3, $result->totalPages);
        $this->assertTrue($result->hasNext);
        $this->assertTrue($result->hasPrevious);
        $this->assertCount(10, $result->items);

        // DESC order default
        $this->assertSame('evt-15', $result->items[0]->eventId);
        $this->assertSame('evt-6', $result->items[9]->eventId);
    }

    public function testItFiltersByActorTypeOnlyAndActorIdOnlyIndependently(): void
    {
        $this->insertLog(eventId: 'evt-1', actorType: 'SYS', actorId: 1);
        $this->insertLog(eventId: 'evt-2', actorType: 'SYS', actorId: 2);
        $this->insertLog(eventId: 'evt-3', actorType: 'USER', actorId: 1);

        $resultTypeOnly = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO(actorType: 'SYS'));
        $this->assertSame(2, $resultTypeOnly->filtered);
        $this->assertSame('evt-2', $resultTypeOnly->items[0]->eventId);
        $this->assertSame('evt-1', $resultTypeOnly->items[1]->eventId);

        $resultIdOnly = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO(actorId: 1));
        $this->assertSame(2, $resultIdOnly->filtered);
        $this->assertSame('evt-3', $resultIdOnly->items[0]->eventId);
        $this->assertSame('evt-1', $resultIdOnly->items[1]->eventId);
    }

    public function testItFiltersByAllIndependentFilters(): void
    {
        $this->insertLog(eventId: 'evt-1', eventKey: 'sys.start', severity: 'WARNING', requestId: 'req-1', correlationId: 'corr-1');
        $this->insertLog(eventId: 'evt-2', eventKey: 'sys.start', severity: 'WARNING', requestId: 'req-2', correlationId: 'corr-1');
        $this->insertLog(eventId: 'evt-3', eventKey: 'sys.stop', severity: 'INFO', requestId: 'req-1', correlationId: 'corr-2');
        $this->insertLog(eventId: 'evt-4', eventKey: 'sys.start', severity: 'ERROR', requestId: 'req-1', correlationId: 'corr-1');

        $result = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO(
            eventKey: 'sys.start',
            severity: 'WARNING',
            requestId: 'req-1',
            correlationId: 'corr-1'
        ));

        $this->assertSame(1, $result->filtered);
        $this->assertSame('evt-1', $result->items[0]->eventId);
    }

    public function testItFiltersByInclusiveDateBoundariesWithMicroseconds(): void
    {
        $this->insertLog(eventId: 'evt-1', occurredAt: '2025-01-01 10:00:00.123456');
        $this->insertLog(eventId: 'evt-2', occurredAt: '2025-01-01 10:00:01.000000');
        $this->insertLog(eventId: 'evt-3', occurredAt: '2025-01-01 10:00:01.999999');
        $this->insertLog(eventId: 'evt-4', occurredAt: '2025-01-01 10:00:02.000000');

        $after = new DateTimeImmutable('2025-01-01T10:00:00.123456Z');
        $before = new DateTimeImmutable('2025-01-01T10:00:01.999999Z');

        $result = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO(
            after: $after,
            before: $before
        ));

        $this->assertSame(3, $result->filtered);
        $this->assertCount(3, $result->items);
        $this->assertSame('evt-3', $result->items[0]->eventId);
        $this->assertSame('evt-2', $result->items[1]->eventId);
        $this->assertSame('evt-1', $result->items[2]->eventId);
    }

    public function testItHydratesDTOsAndMetadataCorrectlyIncludingNumericArrays(): void
    {
        $this->insertLog(
            eventId: 'evt-1',
            durationMs: 450,
            metadata: '{"key": "value"}',
        );

        $this->insertLog(
            eventId: 'evt-2',
            durationMs: null,
            metadata: '[1, 2, 3]', // Numeric array accepted
        );

        $this->insertLog(
            eventId: 'evt-3',
            metadata: '"scalar"', // Invalid, becomes null
        );

        $result = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO());

        $this->assertCount(3, $result->items);

        // DESC sort means evt-3, evt-2, evt-1
        $this->assertSame('evt-3', $result->items[0]->eventId);
        $this->assertNull($result->items[0]->metadata);

        $this->assertSame('evt-2', $result->items[1]->eventId);
        $this->assertSame([1, 2, 3], $result->items[1]->metadata);

        $this->assertSame('evt-1', $result->items[2]->eventId);
        $this->assertSame(450, $result->items[2]->durationMs);
        $this->assertSame(['key' => 'value'], $result->items[2]->metadata);
    }

    public function testItAppliesTieBreakerSort(): void
    {
        // Same time, different IDs
        $this->insertLog(eventId: 'evt-1', occurredAt: '2025-01-01 10:00:00.000000');
        $this->insertLog(eventId: 'evt-2', occurredAt: '2025-01-01 10:00:00.000000');

        $result = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO());

        // ID DESC tie breaker
        $this->assertSame('evt-2', $result->items[0]->eventId);
        $this->assertSame('evt-1', $result->items[1]->eventId);
    }

    public function testItPreservesCallerOwnedTransaction(): void
    {
        $this->pdo->beginTransaction();

        $this->insertLog(eventId: 'evt-tx');

        $result = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO());
        $this->assertSame(1, $result->filtered);

        $this->pdo->rollBack();

        $resultAfterRollback = $this->repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO());
        $this->assertSame(0, $resultAfterRollback->filtered);
    }
}
