<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingPdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository
 */
final class AuthoritativeAuditQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testProtectedPublicPrimitiveSurface(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $query = new AuthoritativeAuditQueryDTO();
        $this->assertNull($query->cursorOccurredAt);
        $this->assertNull($query->cursorId);
        $this->assertSame(50, $query->limit);

        $repository->find($query);

        $expectedSerialized = [
            'after' => null,
            'before' => null,
            'actorType' => null,
            'actorId' => null,
            'targetType' => null,
            'targetId' => null,
            'action' => null,
            'correlationId' => null,
            'cursorOccurredAt' => null,
            'cursorId' => null,
            'limit' => 50,
        ];

        $this->assertSame($expectedSerialized, $query->jsonSerialize());
    }

    public function testCursorCorrectionWithoutBehaviorDrift(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $cursorTime = new DateTimeImmutable('2023-01-01T12:00:00.123456Z');

        $query = new AuthoritativeAuditQueryDTO(
            cursorOccurredAt: $cursorTime,
            cursorId: 42,
            limit: 10
        );

        $repository->find($query);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtRaw */
        $stmtRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmt */
        $stmt = $stmtRaw;

        $sql = $stmt->queryString;
        $params = $stmt->executedParams;

        $this->assertStringContainsString('SELECT * FROM maa_event_logging_authoritative_audit_log', $sql);
        $this->assertStringContainsString('(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))', $sql);
        $this->assertStringNotContainsString(':cursor_at ', $sql); // ensure old one is absent

        $this->assertStringContainsString('ORDER BY occurred_at DESC, id DESC LIMIT 10', $sql);

        $this->assertSame('2023-01-01 12:00:00.123456', $params['cursor_at_before']);
        $this->assertSame('2023-01-01 12:00:00.123456', $params['cursor_at_equal']);
        $this->assertSame(42, $params['cursor_id']);

        // Zero/negative limit clamped
        $pdo->lastStatement = null;
        $queryClamp = new AuthoritativeAuditQueryDTO(limit: -5);
        $repository->find($queryClamp);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtClampRaw */
        $stmtClampRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtClampRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtClamp */
        $stmtClamp = $stmtClampRaw;
        $this->assertStringContainsString('LIMIT 1', $stmtClamp->queryString);

        // Missing component means no cursor predicate
        $pdo->lastStatement = null;
        $queryMissing = new AuthoritativeAuditQueryDTO(cursorOccurredAt: $cursorTime);
        $repository->find($queryMissing);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtMissingRaw */
        $stmtMissingRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtMissingRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtMissing */
        $stmtMissing = $stmtMissingRaw;
        $this->assertStringNotContainsString('cursor_at_before', $stmtMissing->queryString);
    }

    public function testPrimitiveFiltersRemainStable(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $after = new DateTimeImmutable('2023-01-01T10:00:00Z');
        $before = new DateTimeImmutable('2023-01-02T10:00:00Z');

        $query = new AuthoritativeAuditQueryDTO(
            actorType: 'sys',
            actorId: 1,
            targetType: 'file',
            targetId: 2,
            action: 'delete',
            correlationId: 'corr-1',
            after: $after,
            before: $before
        );

        $repository->find($query);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtRaw */
        $stmtRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmt */
        $stmt = $stmtRaw;

        $sql = $stmt->queryString;
        $params = $stmt->executedParams;

        $expectedWhere = 'WHERE actor_type = :actor_type AND actor_id = :actor_id AND target_type = :target_type AND target_id = :target_id AND action = :action AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before';

        $this->assertStringContainsString($expectedWhere, $sql);
        $this->assertSame('sys', $params['actor_type']);
        $this->assertSame(1, $params['actor_id']);
        $this->assertSame('file', $params['target_type']);
        $this->assertSame(2, $params['target_id']);
        $this->assertSame('delete', $params['action']);
        $this->assertSame('corr-1', $params['correlation_id']);
        $this->assertSame('2023-01-01 10:00:00.000000', $params['after']);
        $this->assertSame('2023-01-02 10:00:00.000000', $params['before']);

        // Independent ID filters
        $pdo->lastStatement = null;
        $queryIndependent = new AuthoritativeAuditQueryDTO(actorId: 99, targetId: 88);
        $repository->find($queryIndependent);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtIndependentRaw */
        $stmtIndependentRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtIndependentRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtIndependent */
        $stmtIndependent = $stmtIndependentRaw;
        $this->assertStringContainsString('WHERE actor_id = :actor_id AND target_id = :target_id', $stmtIndependent->queryString);
    }

    public function testHydrationRemainsStable(): void
    {
        $pdo = new class extends FakePdo {
            /** @var array<int, array<string, mixed>> */
            public array $results = [];
            public function prepare(string $query, array $options = []): \PDOStatement {
                /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmt */
                $stmt = parent::prepare($query, $options);
                $stmt->fetchResults = $this->results;
                return $stmt;
            }
        };
        $pdo->results = [
            [
                'id' => '123', // numeric string
                'event_id' => 'uuid-1',
                'actor_type' => 'sys',
                'actor_id' => '42',
                'action' => 'update',
                'target_type' => 'file',
                'target_id' => 100, // integer
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test-agent',
                'correlation_id' => 'corr-1',
                'changes' => '{"foo": "bar"}', // valid associative JSON
                'occurred_at' => '2023-01-01 10:00:00.123456',
            ],
            [
                'id' => 'not-numeric',
                'event_id' => 123, // wrong type
                'actor_type' => null, // missing/null
                'actor_id' => null,
                'action' => null,
                'target_type' => null,
                'target_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'correlation_id' => null,
                'changes' => null,
                'occurred_at' => null,
            ],
            [
                'id' => '2',
                'changes' => '{}', // empty object
            ],
            [
                'id' => '3',
                'changes' => 'not-json', // malformed
            ],
            [
                'id' => '4',
                'changes' => '"scalar"', // scalar
            ],
            [
                'id' => '5',
                'changes' => '[1, 2, 3]', // numeric key
            ]
        ];

        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);
        $results = $repository->find(new AuthoritativeAuditQueryDTO());

        $this->assertCount(6, $results);

        $validDto = $results[0];
        $this->assertSame(123, $validDto->id);
        $this->assertSame('uuid-1', $validDto->eventId);
        $this->assertSame('sys', $validDto->actorType);
        $this->assertSame(42, $validDto->actorId);
        $this->assertSame('update', $validDto->action);
        $this->assertSame('file', $validDto->targetType);
        $this->assertSame(100, $validDto->targetId);
        $this->assertSame(['foo' => 'bar'], $validDto->changes);
        $this->assertSame('2023-01-01 10:00:00.123456', $validDto->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $validDto->occurredAt->getTimezone()->getName());

        $fallbackDto = $results[1];
        $this->assertSame(0, $fallbackDto->id);
        $this->assertSame('', $fallbackDto->eventId);
        $this->assertNull($fallbackDto->actorType);
        $this->assertSame('', $fallbackDto->action);
        $this->assertNull($fallbackDto->changes);
        $this->assertSame('1970-01-01 00:00:00.000000', $fallbackDto->occurredAt->format('Y-m-d H:i:s.u'));

        $this->assertSame([], $results[2]->changes);
        $this->assertNull($results[3]->changes);
        $this->assertNull($results[4]->changes);
        $this->assertNull($results[5]->changes);
    }

    public function testExceptionsRemainStableForPdoFailure(): void
    {
        $pdo = new ThrowingPdo(); // fake PDO internally throws new PDOException('Simulated PDO connection/prepare error');

        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to query AuthoritativeAudit records: Simulated PDO connection/prepare error');

        try {
            $repository->find(new AuthoritativeAuditQueryDTO());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
            throw $e;
        }
    }

    public function testExceptionsRemainStableForStatementFailure(): void
    {
        $pdo = new ThrowingStatementPdo(); // fake statement internally throws new PDOException('Simulated execution error')

        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to query AuthoritativeAudit records: Simulated execution error');

        try {
            $repository->find(new AuthoritativeAuditQueryDTO());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
            throw $e;
        }
    }

    public function testExceptionsRemainStableForMappingFailure(): void
    {
        $pdo = new class extends FakePdo {
            /** @var array<int, array<string, mixed>> */
            public array $results = [];
            public function prepare(string $query, array $options = []): \PDOStatement {
                /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmt */
                $stmt = parent::prepare($query, $options);
                $stmt->fetchResults = $this->results;
                return $stmt;
            }
        };

        $pdo->results = [
            ['occurred_at' => 'invalid-date']
        ];

        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to map AuthoritativeAudit row: Failed to parse time string (invalid-date)');

        try {
            $repository->find(new AuthoritativeAuditQueryDTO());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertInstanceOf(\Exception::class, $e->getPrevious());
            throw $e;
        }
    }
}
