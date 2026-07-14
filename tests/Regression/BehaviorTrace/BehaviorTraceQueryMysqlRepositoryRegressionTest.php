<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\BehaviorTrace;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceQueryInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceCursorDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class BehaviorTraceQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testPrimitiveInterfaceAndRepositoryConstructorRemainUnchanged(): void
    {
        $find = new ReflectionMethod(BehaviorTraceQueryInterface::class, 'find');
        $this->assertSame('find', $find->getName());
        $this->assertSame('array', (string) $find->getReturnType());
        $this->assertSame(BehaviorTraceQueryDTO::class, (string) $find->getParameters()[0]->getType());

        $read = new ReflectionMethod(BehaviorTraceQueryInterface::class, 'read');
        $this->assertSame('read', $read->getName());
        $this->assertSame('iterable', (string) $read->getReturnType());
        $cursorType = $read->getParameters()[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $cursorType);
        $this->assertSame(BehaviorTraceCursorDTO::class, $cursorType->getName());
        $this->assertTrue($read->getParameters()[0]->allowsNull());
        $this->assertSame(100, $read->getParameters()[1]->getDefaultValue());

        $constructor = new ReflectionMethod(BehaviorTraceQueryMysqlRepository::class, '__construct');
        $this->assertSame(['pdo', 'policy'], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));
        $this->assertSame('PDO', (string) $constructor->getParameters()[0]->getType());
        $this->assertTrue($constructor->getParameters()[1]->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[1]->getDefaultValue());
    }

    public function testPrimitiveQueryDtoConstructorAndCursorFieldsRemainUnchanged(): void
    {
        $constructor = new ReflectionMethod(BehaviorTraceQueryDTO::class, '__construct');
        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'entityType',
            'entityId',
            'action',
            'requestId',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));

        $query = new BehaviorTraceQueryDTO();
        $this->assertNull($query->cursorOccurredAt);
        $this->assertNull($query->cursorId);
        $this->assertSame(50, $query->limit);
    }

    public function testFindUsesDescendingOrderingLimitAndDistinctCursorPlaceholders(): void
    {
        $pdo = new FakePdo();
        $repository = new BehaviorTraceQueryMysqlRepository($pdo);
        $cursorAt = new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'));

        $repository->find(new BehaviorTraceQueryDTO(cursorOccurredAt: $cursorAt, cursorId: 10, limit: 25));

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('SELECT *', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))', $pdo->lastStatement->queryString);
        $this->assertStringNotContainsString(':cursor_at OR', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('ORDER BY occurred_at DESC, id DESC', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('LIMIT 25', $pdo->lastStatement->queryString);
        $this->assertSame('2024-01-01 12:00:00.123456', $pdo->lastStatement->executedParams['cursor_at_before']);
        $this->assertSame('2024-01-01 12:00:00.123456', $pdo->lastStatement->executedParams['cursor_at_equal']);
        $this->assertSame(10, $pdo->lastStatement->executedParams['cursor_id']);
    }

    public function testReadUsesAscendingStreamBehaviorAndExistingExceptionType(): void
    {
        $pdo = new FakePdo();
        $repository = new BehaviorTraceQueryMysqlRepository($pdo);
        iterator_to_array($repository->read(new BehaviorTraceCursorDTO(
            lastOccurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            lastId: 99,
        ), 15));

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('ORDER BY occurred_at ASC, id ASC LIMIT :limit', $pdo->lastStatement->queryString);
        $this->assertSame(15, $pdo->lastStatement->boundValues[':limit']);
        $this->assertSame(99, $pdo->lastStatement->boundValues[':last_id']);
        $this->assertInstanceOf(\Throwable::class, new BehaviorTraceStorageException());
    }

    public function testSupersededBehaviorTraceWrapperArtifactsAreAbsent(): void
    {
        $this->assertFalse(class_exists('Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryCursorDTO'));
        $this->assertFalse(class_exists('Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryPageDTO'));
        $this->assertFalse(interface_exists('Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePaginatedQueryInterface'));
        $this->assertFalse(class_exists('Maatify\EventLogging\BehaviorTrace\Service\BehaviorTracePaginatedQueryService'));
    }
}
