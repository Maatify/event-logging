<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditQueryMysqlRepositoryTest extends TestCase
{
    public function testFindWithNoFiltersReturnsDefaultQuery(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $query = new AuthoritativeAuditQueryDTO(limit: 50);
        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertEquals(
            'SELECT * FROM maa_event_logging_authoritative_audit_log  ORDER BY occurred_at DESC, id DESC LIMIT 50',
            $pdo->lastStatement->queryString
        );
        $this->assertEmpty($pdo->lastStatement->executedParams);
    }

    public function testFindWithFiltersConstructsCorrectWhereClause(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $query = new AuthoritativeAuditQueryDTO(
            actorType: 'user',
            actorId: 123,
            targetType: 'account',
            targetId: 456,
            action: 'login',
            correlationId: 'req_123',
            after: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            limit: 10
        );

        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE actor_type = :actor_type AND actor_id = :actor_id AND target_type = :target_type AND target_id = :target_id AND action = :action AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('user', $params['actor_type']);
        $this->assertEquals(123, $params['actor_id']);
        $this->assertEquals('account', $params['target_type']);
        $this->assertEquals(456, $params['target_id']);
        $this->assertEquals('login', $params['action']);
        $this->assertEquals('req_123', $params['correlationId'] ?? $params['correlation_id']);
        $this->assertEquals('2024-01-01 00:00:00.000000', $params['after']);
        $this->assertEquals('2024-01-02 00:00:00.000000', $params['before']);
    }

    public function testFindWithCursorAppliesCursorConditions(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $query = new AuthoritativeAuditQueryDTO(
            cursorOccurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            cursorId: 999,
            limit: 10
        );

        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE (occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('2024-01-01 12:00:00.000000', $params['cursor_at_before']);
        $this->assertEquals('2024-01-01 12:00:00.000000', $params['cursor_at_equal']);
        $this->assertEquals(999, $params['cursor_id']);
    }

    public function testFindMapsRowsToDtoCorrectly(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        // Pre-setup the fake statement before it is returned by prepare
        // Actually, we can't easily preset the results in FakePdo without modifying it slightly,
        // let's do a quick override or use a mock for PDO for this specific test where we need returns
        // Wait, FakePdo stores statements by query string, but we can just use PHPUnit mocks here for simplicity of returning rows
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'event_id' => 'evt_1',
                'actor_type' => 'user',
                'actor_id' => 123,
                'action' => 'login',
                'target_type' => null,
                'target_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Browser',
                'correlation_id' => 'req_1',
                'changes' => '{"foo":"bar"}',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repoWithMock = new AuthoritativeAuditQueryMysqlRepository($mockPdo);
        $results = $repoWithMock->find(new AuthoritativeAuditQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $dto = $results[0];
        $this->assertEquals(1, $dto->id);
        $this->assertEquals('evt_1', $dto->eventId);
        $this->assertEquals('user', $dto->actorType);
        $this->assertEquals(123, $dto->actorId);
        $this->assertEquals('login', $dto->action);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertEquals('127.0.0.1', $dto->ipAddress);
        $this->assertEquals('Browser', $dto->userAgent);
        $this->assertEquals('req_1', $dto->correlationId);
        $this->assertEquals(['foo' => 'bar'], $dto->changes);
        $this->assertEquals('2024-01-01 12:00:00', $dto->occurredAt->format('Y-m-d H:i:s'));
    }

    public function testFindReturnsEmptyArrayOnEmptyResult(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new AuthoritativeAuditQueryMysqlRepository($mockPdo);
        $results = $repository->find(new AuthoritativeAuditQueryDTO(limit: 10));

        $this->assertEmpty($results);
    }

    public function testFindHandlesCorruptJsonByReturningNull(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'event_id' => 'evt_1',
                'changes' => '{corrupt_json',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new AuthoritativeAuditQueryMysqlRepository($mockPdo);
        $results = $repository->find(new AuthoritativeAuditQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->changes);
    }

    public function testFindThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to query AuthoritativeAudit records');

        $repository->find(new AuthoritativeAuditQueryDTO(limit: 10));
    }
}
