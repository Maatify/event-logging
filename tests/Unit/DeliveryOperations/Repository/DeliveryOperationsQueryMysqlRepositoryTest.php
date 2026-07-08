<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsQueryDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class DeliveryOperationsQueryMysqlRepositoryTest extends TestCase
{
    public function testFindWithNoFiltersReturnsDefaultQuery(): void
    {
        $pdo = new FakePdo();
        $repository = new DeliveryOperationsQueryMysqlRepository($pdo);

        $query = new DeliveryOperationsQueryDTO(limit: 50);
        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertEquals(
            'SELECT * FROM maa_event_logging_delivery_operations  ORDER BY occurred_at DESC, id DESC LIMIT 50',
            $pdo->lastStatement->queryString
        );
        $this->assertEmpty($pdo->lastStatement->executedParams);
    }

    public function testFindWithFiltersConstructsCorrectWhereClause(): void
    {
        $pdo = new FakePdo();
        $repository = new DeliveryOperationsQueryMysqlRepository($pdo);

        $query = new DeliveryOperationsQueryDTO(
            actorType: 'user',
            actorId: 123,
            targetType: 'account',
            targetId: 456,
            channel: 'email',
            operationType: 'send',
            status: 'failed',
            requestId: 'req_1',
            correlationId: 'corr_1',
            after: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            limit: 10
        );

        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE actor_type = :actor_type AND actor_id = :actor_id AND target_type = :target_type AND target_id = :target_id AND channel = :channel AND operation_type = :operation_type AND status = :status AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('user', $params['actor_type']);
        $this->assertEquals(123, $params['actor_id']);
        $this->assertEquals('account', $params['target_type']);
        $this->assertEquals(456, $params['target_id']);
        $this->assertEquals('email', $params['channel']);
        $this->assertEquals('send', $params['operation_type']);
        $this->assertEquals('failed', $params['status']);
        $this->assertEquals('req_1', $params['request_id']);
        $this->assertEquals('corr_1', $params['correlation_id']);
    }

    public function testFindWithCursorAppliesCursorConditions(): void
    {
        $pdo = new FakePdo();
        $repository = new DeliveryOperationsQueryMysqlRepository($pdo);

        $query = new DeliveryOperationsQueryDTO(
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
                'channel' => 'email',
                'operation_type' => 'send',
                'actor_type' => 'user',
                'actor_id' => 123,
                'target_type' => 'account',
                'target_id' => 456,
                'status' => 'sent',
                'attempt_no' => 1,
                'scheduled_at' => null,
                'completed_at' => '2024-01-01 12:00:05.000000',
                'correlation_id' => 'corr_1',
                'request_id' => 'req_1',
                'provider' => 'smtp',
                'provider_message_id' => 'msg_1',
                'error_code' => null,
                'error_message' => null,
                'metadata' => '{"foo":"bar"}',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ]
        ]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new DeliveryOperationsQueryMysqlRepository($mockPdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $dto = $results[0];
        $this->assertEquals('evt_1', $dto->eventId);
        $this->assertEquals('email', $dto->channel);
        $this->assertEquals('send', $dto->operationType);
        $this->assertEquals('user', $dto->actorType);
        $this->assertEquals(123, $dto->actorId);
        $this->assertEquals(['foo' => 'bar'], $dto->metadata);
        $this->assertNull($dto->scheduledAt);
        $this->assertNotNull($dto->completedAt);
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

        $repository = new DeliveryOperationsQueryMysqlRepository($mockPdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO(limit: 10));

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->metadata);
    }

    public function testFindThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new DeliveryOperationsQueryMysqlRepository($pdo);

        $this->expectException(DeliveryOperationsStorageException::class);
        $this->expectExceptionMessage('Failed to query DeliveryOperations records');

        $repository->find(new DeliveryOperationsQueryDTO(limit: 10));
    }

    public function testFindReturnsEmptyArrayOnEmptyResult(): void
    {
        $mockStatement = $this->createMock(\PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([]);

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStatement);

        $repository = new DeliveryOperationsQueryMysqlRepository($mockPdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO(limit: 10));

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}