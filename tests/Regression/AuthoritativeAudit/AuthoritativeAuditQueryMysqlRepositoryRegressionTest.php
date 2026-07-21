<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use ReflectionNamedType;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use ReflectionClass;
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
        $interfaceReflector = new ReflectionClass(AuthoritativeAuditQueryInterface::class);
        $findMethod = $interfaceReflector->getMethod('find');
        $param = $findMethod->getParameters()[0];

        $this->assertFalse($param->allowsNull());
        $this->assertFalse($param->isDefaultValueAvailable());

        /** @var \ReflectionNamedType $paramType */
        $paramType = $param->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $paramType);
        $this->assertSame(AuthoritativeAuditQueryDTO::class, $paramType->getName());

        /** @var \ReflectionNamedType $returnType */
        $returnType = $findMethod->getReturnType();
        $this->assertFalse($returnType->allowsNull());
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertSame('array', $returnType->getName());

        $this->assertStringContainsString('@return array<AuthoritativeAuditViewDTO>', (string) $findMethod->getDocComment());
        $this->assertStringContainsString('@throws AuthoritativeAuditStorageException', (string) $findMethod->getDocComment());

        $repositoryReflector = new ReflectionClass(AuthoritativeAuditQueryMysqlRepository::class);
        $constructor = $repositoryReflector->getMethod('__construct');
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $pdoParam = $constructor->getParameters()[0];
        $this->assertSame('pdo', $pdoParam->getName());

        /** @var \ReflectionNamedType $pdoParamType */
        $pdoParamType = $pdoParam->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $pdoParamType);
        $this->assertSame(PDO::class, $pdoParamType->getName());

        $pdoProp = $repositoryReflector->getProperty('pdo');
        $this->assertTrue($pdoProp->isPrivate());
        $this->assertTrue($pdoProp->isReadOnly());

        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $query = new AuthoritativeAuditQueryDTO();
        $this->assertNull($query->cursorOccurredAt);
        $this->assertNull($query->cursorId);
        $this->assertSame(50, $query->limit);

        $repository->find($query);

        $dtoReflector = new ReflectionClass(AuthoritativeAuditQueryDTO::class);
        $this->assertTrue($dtoReflector->isFinal());
        $this->assertTrue($dtoReflector->isReadOnly());

        $dtoConstructor = $dtoReflector->getMethod('__construct');
        $expectedParams = [
            'after' => ['DateTimeImmutable', true, true, null],
            'before' => ['DateTimeImmutable', true, true, null],
            'actorType' => ['string', true, true, null],
            'actorId' => ['int', true, true, null],
            'targetType' => ['string', true, true, null],
            'targetId' => ['int', true, true, null],
            'action' => ['string', true, true, null],
            'correlationId' => ['string', true, true, null],
            'cursorOccurredAt' => ['DateTimeImmutable', true, true, null],
            'cursorId' => ['int', true, true, null],
            'limit' => ['int', false, true, 50]
        ];

        $actualParams = [];
        foreach ($dtoConstructor->getParameters() as $p) {
            $t = $p->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $t);
            /** @var \ReflectionNamedType $t */
            $actualParams[$p->getName()] = [
                $t->getName(),
                $p->allowsNull(),
                $p->isDefaultValueAvailable(),
                $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null
            ];
        }
        $this->assertSame(array_keys($expectedParams), array_keys($actualParams));
        $this->assertSame($expectedParams, $actualParams);

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

        $date = new DateTimeImmutable('2023-01-01T10:00:00.123456Z');
        $queryFull = new AuthoritativeAuditQueryDTO(
            actorType: 'sys',
            actorId: 42,
            targetType: 'file',
            targetId: 100,
            action: 'update',
            correlationId: 'corr-1',
            after: $date,
            before: $date,
            cursorOccurredAt: $date,
            cursorId: 99,
            limit: 10
        );

        $expectedFullSerialized = [
            'after' => '2023-01-01T10:00:00+00:00',
            'before' => '2023-01-01T10:00:00+00:00',
            'actorType' => 'sys',
            'actorId' => 42,
            'targetType' => 'file',
            'targetId' => 100,
            'action' => 'update',
            'correlationId' => 'corr-1',
            'cursorOccurredAt' => '2023-01-01T10:00:00+00:00',
            'cursorId' => 99,
            'limit' => 10,
        ];

        $this->assertSame($expectedFullSerialized, $queryFull->jsonSerialize());

        $viewDtoReflector = new ReflectionClass(AuthoritativeAuditViewDTO::class);
        $this->assertTrue($viewDtoReflector->isFinal());
        $this->assertTrue($viewDtoReflector->isReadOnly());

        $viewConstructor = $viewDtoReflector->getMethod('__construct');
        $expectedViewParams = [
            'id' => ['int', false, false],
            'eventId' => ['string', false, false],
            'actorType' => ['string', true, false],
            'actorId' => ['int', true, false],
            'action' => ['string', false, false],
            'targetType' => ['string', true, false],
            'targetId' => ['int', true, false],
            'ipAddress' => ['string', true, false],
            'userAgent' => ['string', true, false],
            'correlationId' => ['string', true, false],
            'changes' => ['array', true, false],
            'occurredAt' => ['DateTimeImmutable', false, false]
        ];

        $actualViewParams = [];
        foreach ($viewConstructor->getParameters() as $p) {
            $t = $p->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $t);
            /** @var \ReflectionNamedType $t */
            $actualViewParams[$p->getName()] = [
                $t->getName(),
                $p->allowsNull(),
                $p->isDefaultValueAvailable()
            ];
        }
        $this->assertSame(array_keys($expectedViewParams), array_keys($actualViewParams));
        $this->assertSame($expectedViewParams, $actualViewParams);

        $viewDto = new AuthoritativeAuditViewDTO(
            id: 1,
            eventId: 'uuid',
            actorType: 'sys',
            actorId: 42,
            action: 'update',
            targetType: 'file',
            targetId: 100,
            ipAddress: '127.0.0.1',
            userAgent: 'agent',
            correlationId: 'corr-1',
            changes: ['foo' => 'bar'],
            occurredAt: $date
        );

        $expectedViewSerialized = [
            'id' => 1,
            'eventId' => 'uuid',
            'actorType' => 'sys',
            'actorId' => 42,
            'action' => 'update',
            'targetType' => 'file',
            'targetId' => 100,
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'agent',
            'correlationId' => 'corr-1',
            'changes' => ['foo' => 'bar'],
            'occurredAt' => '2023-01-01T10:00:00+00:00',
        ];
        $this->assertSame($expectedViewSerialized, $viewDto->jsonSerialize());
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

                // Zero limit clamped
        $pdo->lastStatement = null;
        $queryClamp = new AuthoritativeAuditQueryDTO(limit: 0);
        $repository->find($queryClamp);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtClampRaw */
        $stmtClampRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtClampRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtClamp */
        $stmtClamp = $stmtClampRaw;
        $this->assertStringContainsString('LIMIT 1', $stmtClamp->queryString);

        // Negative limit clamped
        $pdo->lastStatement = null;
        $queryClampNegative = new AuthoritativeAuditQueryDTO(limit: -5);
        $repository->find($queryClampNegative);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtClampNegativeRaw */
        $stmtClampNegativeRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtClampNegativeRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtClampNegative */
        $stmtClampNegative = $stmtClampNegativeRaw;
        $this->assertStringContainsString('LIMIT 1', $stmtClampNegative->queryString);

        // Missing component means no cursor predicate - ID only
        $pdo->lastStatement = null;
        $queryMissingDate = new AuthoritativeAuditQueryDTO(cursorId: 42);
        $repository->find($queryMissingDate);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtMissingDateRaw */
        $stmtMissingDateRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtMissingDateRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtMissingDate */
        $stmtMissingDate = $stmtMissingDateRaw;
        $this->assertStringNotContainsString('cursor_at_before', $stmtMissingDate->queryString);

        // Missing component means no cursor predicate - Date only
        $pdo->lastStatement = null;
        $queryMissingId = new AuthoritativeAuditQueryDTO(cursorOccurredAt: $cursorTime);
        $repository->find($queryMissingId);

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtMissingIdRaw */
        $stmtMissingIdRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtMissingIdRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtMissingId */
        $stmtMissingId = $stmtMissingIdRaw;
        $this->assertStringNotContainsString('cursor_at_before', $stmtMissingId->queryString);
    }

    public function testPrimitiveFiltersRemainStable(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $after = new DateTimeImmutable('2023-01-01T10:00:00Z');
        $before = new DateTimeImmutable('2023-01-02T10:00:00Z');

        $filters = [
            ['actorType', 'sys', 'actor_type = :actor_type', ['actor_type' => 'sys']],
            ['actorId', 1, 'actor_id = :actor_id', ['actor_id' => 1]],
            ['targetType', 'file', 'target_type = :target_type', ['target_type' => 'file']],
            ['targetId', 2, 'target_id = :target_id', ['target_id' => 2]],
            ['action', 'delete', 'action = :action', ['action' => 'delete']],
            ['correlationId', 'corr-1', 'correlation_id = :correlation_id', ['correlation_id' => 'corr-1']],
        ];

        foreach ($filters as [$field, $value, $expectedCondition, $expectedParams]) {
            $pdo->lastStatement = null;
            $query = new AuthoritativeAuditQueryDTO(
                actorType: $field === 'actorType' ? $value : null,
                actorId: $field === 'actorId' ? $value : null,
                targetType: $field === 'targetType' ? $value : null,
                targetId: $field === 'targetId' ? $value : null,
                action: $field === 'action' ? $value : null,
                correlationId: $field === 'correlationId' ? $value : null
            );
            $repository->find($query);

            /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtRaw */
            $stmtRaw = $pdo->lastStatement;
            $this->assertNotNull($stmtRaw);
            /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmt */
            $stmt = $stmtRaw;

            $this->assertStringContainsString('WHERE ' . $expectedCondition . ' ORDER BY', $stmt->queryString);
            $this->assertSame($expectedParams, $stmt->executedParams);
        }

        // Date filters
        $pdo->lastStatement = null;
        $repository->find(new AuthoritativeAuditQueryDTO(after: $after));
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtAfterRaw */
        $stmtAfterRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtAfterRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtAfter */
        $stmtAfter = $stmtAfterRaw;
        $this->assertStringContainsString('WHERE occurred_at >= :after ORDER BY', $stmtAfter->queryString);
        $this->assertSame(['after' => '2023-01-01 10:00:00.000000'], $stmtAfter->executedParams);

        $pdo->lastStatement = null;
        $repository->find(new AuthoritativeAuditQueryDTO(before: $before));
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtBeforeRaw */
        $stmtBeforeRaw = $pdo->lastStatement;
        $this->assertNotNull($stmtBeforeRaw);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtBefore */
        $stmtBefore = $stmtBeforeRaw;
        $this->assertStringContainsString('WHERE occurred_at <= :before ORDER BY', $stmtBefore->queryString);
        $this->assertSame(['before' => '2023-01-02 10:00:00.000000'], $stmtBefore->executedParams);

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

        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement|null $stmtRawAll */
        $stmtRawAll = $pdo->lastStatement;
        $this->assertNotNull($stmtRawAll);
        /** @var \Maatify\EventLogging\Tests\Support\FakeStatement $stmtAll */
        $stmtAll = $stmtRawAll;

        $sql = $stmtAll->queryString;
        $params = $stmtAll->executedParams;

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
            /** @var array<int, mixed> */
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
            false, // Non-array row is skipped
            'not-an-array', // Non-array row is skipped
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
            ],
            [
                'id' => '6',
                'changes' => '{"0": "a", "foo": "bar"}', // mixed key
            ],
            [
                'id' => '7',
                'changes' => 123, // not string
            ],
            [
                'id' => '8',
                'changes' => '', // empty string
            ],
            [
                'id' => '9', // missing
            ]
        ];

        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);
        $results = $repository->find(new AuthoritativeAuditQueryDTO());

        $this->assertCount(10, $results); // 12 rows returned, 2 skipped

        $validDto = $results[0];
        $this->assertSame(123, $validDto->id);
        $this->assertSame('uuid-1', $validDto->eventId);
        $this->assertSame('sys', $validDto->actorType);
        $this->assertSame(42, $validDto->actorId);
        $this->assertSame('update', $validDto->action);
        $this->assertSame('file', $validDto->targetType);
        $this->assertSame(100, $validDto->targetId);
        $this->assertSame('127.0.0.1', $validDto->ipAddress);
        $this->assertSame('test-agent', $validDto->userAgent);
        $this->assertSame('corr-1', $validDto->correlationId);
        $this->assertSame(['foo' => 'bar'], $validDto->changes);
        $this->assertSame('2023-01-01 10:00:00.123456', $validDto->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $validDto->occurredAt->getTimezone()->getName());

        $fallbackDto = $results[1];
        $this->assertSame(0, $fallbackDto->id);
        $this->assertSame('', $fallbackDto->eventId);
        $this->assertNull($fallbackDto->actorType);
        $this->assertNull($fallbackDto->actorId);
        $this->assertSame('', $fallbackDto->action);
        $this->assertNull($fallbackDto->targetType);
        $this->assertNull($fallbackDto->targetId);
        $this->assertNull($fallbackDto->ipAddress);
        $this->assertNull($fallbackDto->userAgent);
        $this->assertNull($fallbackDto->correlationId);
        $this->assertNull($fallbackDto->changes);
        $this->assertSame('1970-01-01 00:00:00.000000', $fallbackDto->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $fallbackDto->occurredAt->getTimezone()->getName());

        $this->assertSame([], $results[2]->changes);
        $this->assertNull($results[3]->changes);
        $this->assertNull($results[4]->changes);
        $this->assertNull($results[5]->changes);
        $this->assertNull($results[6]->changes);
        $this->assertNull($results[7]->changes);
        $this->assertSame(8, $results[8]->id);
        $this->assertNull($results[8]->changes);
        $this->assertSame(9, $results[9]->id);
        $this->assertNull($results[9]->changes);
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
