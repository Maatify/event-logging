<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryQueryDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryCursorDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class DiagnosticsTelemetryQueryMysqlRepositoryTest extends TestCase
{
    public function testFindWithNoFiltersReturnsDefaultQuery(): void
    {
        $pdo = new FakePdo();
        $repository = new DiagnosticsTelemetryQueryMysqlRepository($pdo);

        $query = new DiagnosticsTelemetryQueryDTO(limit: 50);
        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertEquals(
            'SELECT * FROM maa_event_logging_diagnostics_telemetry  ORDER BY occurred_at DESC, id DESC LIMIT 50',
            $pdo->lastStatement->queryString
        );
        $this->assertEmpty($pdo->lastStatement->executedParams);
    }

    public function testFindWithFiltersConstructsCorrectWhereClause(): void
    {
        $pdo = new FakePdo();
        $repository = new DiagnosticsTelemetryQueryMysqlRepository($pdo);

        $query = new DiagnosticsTelemetryQueryDTO(
            actorType: 'system',
            actorId: 1,
            eventKey: 'db_query',
            severity: 'warning',
            requestId: 'req_1',
            correlationId: 'corr_1',
            after: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            limit: 10
        );

        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE actor_type = :actor_type AND actor_id = :actor_id AND event_key = :event_key AND severity = :severity AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('system', $params['actor_type']);
        $this->assertEquals(1, $params['actor_id']);
        $this->assertEquals('db_query', $params['event_key']);
        $this->assertEquals('warning', $params['severity']);
        $this->assertEquals('req_1', $params['request_id']);
        $this->assertEquals('corr_1', $params['correlation_id']);
    }

    public function testFindWithCursorAppliesCursorConditions(): void
    {
        $pdo = new FakePdo();
        $repository = new DiagnosticsTelemetryQueryMysqlRepository($pdo);

        $query = new DiagnosticsTelemetryQueryDTO(
            cursorOccurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            cursorId: 999,
            limit: 10
        );

        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE (occurred_at < :cursor_at OR (occurred_at = :cursor_at AND id < :cursor_id))', $pdo->lastStatement->queryString);
    }

    public function testFindMapsRowsToDtoCorrectly(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'event_id' => 'evt_1',
                'event_key' => 'db_query',
                'severity' => 'WARNING',
                'actor_type' => 'SYSTEM',
                'actor_id' => 123,
                'correlation_id' => 'corr_1',
                'request_id' => 'req_1',
                'route_name' => 'home',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Browser',
                'duration_ms' => 150,
                'metadata' => '{"foo":"bar"}',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new DiagnosticsTelemetryQueryMysqlRepository($mockPdo);
        $results = $repository->find(new DiagnosticsTelemetryQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $dto = $results[0];
        $this->assertEquals('evt_1', $dto->eventId);
        $this->assertEquals('db_query', $dto->eventKey);
        $this->assertEquals('WARNING', $dto->severity->value());
        $this->assertEquals('SYSTEM', $dto->context->actorType->value());
        $this->assertEquals(123, $dto->context->actorId);
        $this->assertEquals(150, $dto->durationMs);
        $this->assertEquals(['foo' => 'bar'], $dto->metadata);
    }

    public function testFindHandlesCorruptJsonByReturningNull(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'event_id' => 'evt_1',
                'metadata' => '{corrupt_json',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new DiagnosticsTelemetryQueryMysqlRepository($mockPdo);
        $results = $repository->find(new DiagnosticsTelemetryQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->metadata);
    }

    public function testReadLegacyMethodYieldsCorrectly(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);

        $mockStatement->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'id' => 1,
                'event_id' => 'evt_1',
                'metadata' => '{}',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ],
            [
                'id' => 2,
                'event_id' => 'evt_2',
                'metadata' => '{}',
                'occurred_at' => '2024-01-01 12:00:01.000000',
            ],
            false
        );

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new DiagnosticsTelemetryQueryMysqlRepository($mockPdo);
        $cursor = new DiagnosticsTelemetryCursorDTO(new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')), 0);
        $generator = $repository->read($cursor, 10);

        $results = iterator_to_array($generator);

        $this->assertCount(2, $results);
        $this->assertEquals('evt_1', $results[0]->eventId);
        $this->assertEquals('evt_2', $results[1]->eventId);
    }

    public function testFindThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new DiagnosticsTelemetryQueryMysqlRepository($pdo);

        $this->expectException(DiagnosticsTelemetryStorageException::class);
        $this->expectExceptionMessage('Failed to query DiagnosticsTelemetry records');

        $repository->find(new DiagnosticsTelemetryQueryDTO(limit: 10));
    }

    public function testFindReturnsEmptyArrayOnEmptyResult(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new DiagnosticsTelemetryQueryMysqlRepository($mockPdo);
        $results = $repository->find(new DiagnosticsTelemetryQueryDTO(limit: 10));

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}