<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class SecuritySignalsQueryMysqlRepositoryTest extends TestCase
{
    public function testFindWithNoFiltersReturnsDefaultQuery(): void
    {
        $pdo = new FakePdo();
        $repository = new SecuritySignalsQueryMysqlRepository($pdo);

        $query = new SecuritySignalsQueryDTO(limit: 50);
        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertEquals(
            'SELECT * FROM maa_event_logging_security_signals  ORDER BY occurred_at DESC, id DESC LIMIT 50',
            $pdo->lastStatement->queryString
        );
        $this->assertEmpty($pdo->lastStatement->executedParams);
    }

    public function testFindWithFiltersConstructsCorrectWhereClause(): void
    {
        $pdo = new FakePdo();
        $repository = new SecuritySignalsQueryMysqlRepository($pdo);

        $query = new SecuritySignalsQueryDTO(
            actorType: 'user',
            actorId: 123,
            signalType: 'login_failed',
            severity: 'high',
            requestId: 'req_1',
            correlationId: 'corr_1',
            after: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            limit: 10
        );

        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE actor_type = :actor_type AND actor_id = :actor_id AND signal_type = :signal_type AND severity = :severity AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('user', $params['actor_type']);
        $this->assertEquals(123, $params['actor_id']);
        $this->assertEquals('login_failed', $params['signal_type']);
        $this->assertEquals('high', $params['severity']);
        $this->assertEquals('req_1', $params['request_id']);
        $this->assertEquals('corr_1', $params['correlation_id']);
    }

    public function testFindWithCursorAppliesCursorConditions(): void
    {
        $pdo = new FakePdo();
        $repository = new SecuritySignalsQueryMysqlRepository($pdo);

        $query = new SecuritySignalsQueryDTO(
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
                'actor_type' => 'user',
                'actor_id' => 123,
                'signal_type' => 'login_failed',
                'severity' => 'high',
                'correlation_id' => 'corr_1',
                'request_id' => 'req_1',
                'route_name' => 'login',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Browser',
                'metadata' => '{"foo":"bar"}',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new SecuritySignalsQueryMysqlRepository($mockPdo);
        $results = $repository->find(new SecuritySignalsQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $dto = $results[0];
        $this->assertEquals('evt_1', $dto->eventId);
        $this->assertEquals('user', $dto->actorType);
        $this->assertEquals(123, $dto->actorId);
        $this->assertEquals('login_failed', $dto->signalType);
        $this->assertEquals('high', $dto->severity);
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

        $repository = new SecuritySignalsQueryMysqlRepository($mockPdo);
        $results = $repository->find(new SecuritySignalsQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->metadata);
    }

    public function testFindThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new SecuritySignalsQueryMysqlRepository($pdo);

        $this->expectException(SecuritySignalsStorageException::class);
        $this->expectExceptionMessage('Failed to query SecuritySignals records');

        $repository->find(new SecuritySignalsQueryDTO(limit: 10));
    }
}
