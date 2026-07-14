<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class AuditTrailQueryMysqlRepositoryTest extends TestCase
{
    public function testFindWithNoFiltersReturnsDefaultQuery(): void
    {
        $pdo = new FakePdo();
        $repository = new AuditTrailQueryMysqlRepository($pdo);

        $query = new AuditTrailQueryDTO(limit: 50);
        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('SELECT *', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('FROM maa_event_logging_audit_trail', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('ORDER BY occurred_at DESC, id DESC', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('LIMIT 50', $pdo->lastStatement->queryString);
        $this->assertEmpty($pdo->lastStatement->executedParams);
    }

    public function testFindWithFiltersConstructsCorrectWhereClause(): void
    {
        $pdo = new FakePdo();
        $repository = new AuditTrailQueryMysqlRepository($pdo);

        $query = new AuditTrailQueryDTO(
            actorType: 'user',
            actorId: 123,
            eventKey: 'page_view',
            entityType: 'page',
            entityId: 456,
            subjectType: 'session',
            subjectId: 789,
            requestId: 'req_1',
            correlationId: 'corr_1',
            after: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            limit: 10
        );

        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $sql = $pdo->lastStatement->queryString;

        $this->assertStringContainsString('WHERE actor_type = :actor_type AND actor_id = :actor_id AND event_key = :event_key AND entity_type = :entity_type AND entity_id = :entity_id AND subject_type = :subject_type AND subject_id = :subject_id AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before', $sql);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('user', $params['actor_type']);
        $this->assertEquals(123, $params['actor_id']);
        $this->assertEquals('page_view', $params['event_key']);
        $this->assertEquals('page', $params['entity_type']);
        $this->assertEquals(456, $params['entity_id']);
        $this->assertEquals('session', $params['subject_type']);
        $this->assertEquals(789, $params['subject_id']);
        $this->assertEquals('req_1', $params['request_id']);
        $this->assertEquals('corr_1', $params['correlation_id']);
        $this->assertEquals('2024-01-01 00:00:00.000000', $params['after']);
        $this->assertEquals('2024-01-02 00:00:00.000000', $params['before']);
    }

    public function testFindWithCursorAppliesCursorConditions(): void
    {
        $pdo = new FakePdo();
        $repository = new AuditTrailQueryMysqlRepository($pdo);

        $query = new AuditTrailQueryDTO(
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
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'event_id' => 'evt_1',
                'actor_type' => 'user',
                'actor_id' => 123,
                'event_key' => 'page_view',
                'entity_type' => 'page',
                'entity_id' => 456,
                'subject_type' => null,
                'subject_id' => null,
                'referrer_route_name' => 'login',
                'referrer_path' => '/login',
                'referrer_host' => 'example.com',
                'correlation_id' => 'corr_1',
                'request_id' => 'req_1',
                'route_name' => 'home',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Browser',
                'metadata' => '{"foo":"bar"}',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new AuditTrailQueryMysqlRepository($mockPdo);
        $results = $repository->find(new AuditTrailQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $dto = $results[0];
        $this->assertEquals(1, $dto->id);
        $this->assertEquals('evt_1', $dto->eventId);
        $this->assertEquals('user', $dto->actorType);
        $this->assertEquals(123, $dto->actorId);
        $this->assertEquals('page_view', $dto->eventKey);
        $this->assertEquals('page', $dto->entityType);
        $this->assertEquals(456, $dto->entityId);
        $this->assertNull($dto->subjectType);
        $this->assertNull($dto->subjectId);
        $this->assertEquals('login', $dto->referrerRouteName);
        $this->assertEquals(['foo' => 'bar'], $dto->metadata);
        $this->assertEquals('2024-01-01 12:00:00', $dto->occurredAt->format('Y-m-d H:i:s'));
    }

    public function testFindHandlesCorruptJsonByReturningNull(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'metadata' => '{corrupt_json',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new AuditTrailQueryMysqlRepository($mockPdo);
        $results = $repository->find(new AuditTrailQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->metadata);
    }

    public function testFindThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new AuditTrailQueryMysqlRepository($pdo);

        $this->expectException(AuditTrailStorageException::class);
        $this->expectExceptionMessage('Failed to query audit trail');

        $repository->find(new AuditTrailQueryDTO(limit: 10));
    }

    public function testFindReturnsEmptyArrayOnEmptyResult(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new AuditTrailQueryMysqlRepository($mockPdo);
        $results = $repository->find(new AuditTrailQueryDTO(limit: 10));

        $this->assertEmpty($results);
    }
}
