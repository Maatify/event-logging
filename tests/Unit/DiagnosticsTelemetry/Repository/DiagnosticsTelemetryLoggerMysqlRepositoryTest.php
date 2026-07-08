<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryContextDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryLoggerMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class DummyDiagActorType implements DiagnosticsTelemetryActorTypeInterface
{
    public function __construct(private string $value) {}
    public function value(): string { return $this->value; }
}

class DummyDiagSeverity implements DiagnosticsTelemetrySeverityInterface
{
    public function __construct(private string $value) {}
    public function value(): string { return $this->value; }
}

class DiagnosticsTelemetryLoggerMysqlRepositoryTest extends TestCase
{
    public function testWriteExecutesCorrectSqlWithParameters(): void
    {
        $pdo = new FakePdo();
        $repository = new DiagnosticsTelemetryLoggerMysqlRepository($pdo);

        $context = new DiagnosticsTelemetryContextDTO(
            actorType: new DummyDiagActorType('system'),
            actorId: 1,
            correlationId: 'corr_1',
            requestId: 'req_1',
            routeName: 'api.login',
            ipAddress: '127.0.0.1',
            userAgent: 'curl',
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $dto = new DiagnosticsTelemetryEventDTO(
            eventId: 'evt_123',
            eventKey: 'db_query',
            severity: new DummyDiagSeverity('warning'),
            context: $context,
            durationMs: 150,
            metadata: ['query' => 'SELECT 1']
        );

        $repository->write($dto);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('INSERT INTO maa_event_logging_diagnostics_telemetry', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('evt_123', $params[':event_id']);
        $this->assertEquals('db_query', $params[':event_key']);
        $this->assertEquals('warning', $params[':severity']);
        $this->assertEquals('system', $params[':actor_type']);
        $this->assertEquals(1, $params[':actor_id']);
        $this->assertEquals('corr_1', $params[':correlation_id']);
        $this->assertEquals('req_1', $params[':request_id']);
        $this->assertEquals('api.login', $params[':route_name']);
        $this->assertEquals('127.0.0.1', $params[':ip_address']);
        $this->assertEquals('curl', $params[':user_agent']);
        $this->assertEquals(150, $params[':duration_ms']);
        $this->assertEquals('{"query":"SELECT 1"}', $params[':metadata']);
        $this->assertEquals('2024-01-01 12:00:00.000000', $params[':occurred_at']);
    }

    public function testWriteWithEmptyMetadataSetsNull(): void
    {
        $pdo = new FakePdo();
        $repository = new DiagnosticsTelemetryLoggerMysqlRepository($pdo);

        $context = new DiagnosticsTelemetryContextDTO(
            actorType: new DummyDiagActorType('system'),
            actorId: 1,
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $dto = new DiagnosticsTelemetryEventDTO(
            eventId: 'evt_123',
            eventKey: 'db_query',
            severity: new DummyDiagSeverity('warning'),
            context: $context,
            durationMs: null,
            metadata: []
        );

        $repository->write($dto);

        $this->assertNotNull($pdo->lastStatement);
        $params = $pdo->lastStatement->executedParams;
        $this->assertNull($params[':metadata']);
    }

    public function testWriteThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new DiagnosticsTelemetryLoggerMysqlRepository($pdo);

        $context = new DiagnosticsTelemetryContextDTO(
            actorType: new DummyDiagActorType('system'),
            actorId: 1,
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $dto = new DiagnosticsTelemetryEventDTO(
            eventId: 'evt_123',
            eventKey: 'db_query',
            severity: new DummyDiagSeverity('warning'),
            context: $context,
            durationMs: null,
            metadata: []
        );

        $this->expectException(DiagnosticsTelemetryStorageException::class);
        $this->expectExceptionMessage('Failed to write telemetry log');

        $repository->write($dto);
    }
}
