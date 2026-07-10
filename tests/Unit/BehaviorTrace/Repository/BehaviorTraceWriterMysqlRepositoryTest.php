<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceWriterMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class DummyActorType implements BehaviorTraceActorTypeInterface
{
    public function __construct(private string $value) {}
    public function value(): string { return $this->value; }
}

class BehaviorTraceWriterMysqlRepositoryTest extends TestCase
{
    public function testWriteExecutesCorrectSqlWithParameters(): void
    {
        $pdo = new FakePdo();
        $repository = new BehaviorTraceWriterMysqlRepository($pdo);

        $context = new BehaviorTraceContextDTO(
            actorType: new DummyActorType('user'),
            actorId: 456,
            correlationId: 'corr_1',
            requestId: 'req_1',
            routeName: 'home',
            ipAddress: '127.0.0.1',
            userAgent: 'Browser',
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $dto = new BehaviorTraceEventDTO(
            id: 0,
            eventId: 'evt_123',
            action: 'click',
            entityType: 'button',
            entityId: 789,
            metadata: ['x' => 10, 'y' => 20],
            context: $context
        );

        $repository->write($dto);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('INSERT INTO maa_event_logging_behavior_trace', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('evt_123', $params[':event_id']);
        $this->assertEquals('click', $params[':action']);
        $this->assertEquals('button', $params[':entity_type']);
        $this->assertEquals(789, $params[':entity_id']);
        $this->assertEquals('user', $params[':actor_type']);
        $this->assertEquals(456, $params[':actor_id']);
        $this->assertEquals('corr_1', $params[':correlation_id']);
        $this->assertEquals('req_1', $params[':request_id']);
        $this->assertEquals('home', $params[':route_name']);
        $this->assertEquals('127.0.0.1', $params[':ip_address']);
        $this->assertEquals('Browser', $params[':user_agent']);
        $this->assertEquals('{"x":10,"y":20}', $params[':metadata']);
        $this->assertEquals('2024-01-01 12:00:00.000000', $params[':occurred_at']);
    }

    public function testWriteThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new BehaviorTraceWriterMysqlRepository($pdo);

        $context = new BehaviorTraceContextDTO(
            actorType: new DummyActorType('user'),
            actorId: 456,
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $dto = new BehaviorTraceEventDTO(
            id: 0,
            eventId: 'evt_123',
            action: 'click',
            entityType: null,
            entityId: null,
            metadata: [],
            context: $context
        );

        $this->expectException(BehaviorTraceStorageException::class);
        $this->expectExceptionMessage('Failed to write behavior trace');

        $repository->write($dto);
    }
}
