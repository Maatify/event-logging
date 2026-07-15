<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\SecuritySignals;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsQueryInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingPdo;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;

final class SecuritySignalsQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testPrimitiveInterfaceDtoViewAndRepositoryConstructorRemainUnchanged(): void
    {
        $find = new ReflectionMethod(SecuritySignalsQueryInterface::class, 'find');
        $this->assertSame('find', $find->getName());
        $this->assertSame('array', (string) $find->getReturnType());
        $this->assertSame(SecuritySignalsQueryDTO::class, (string) $find->getParameters()[0]->getType());

        $constructor = new ReflectionMethod(SecuritySignalsQueryDTO::class, '__construct');
        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'requestId',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));

        $viewConstructor = new ReflectionMethod(SecuritySignalsViewDTO::class, '__construct');
        $this->assertSame([
            'id',
            'eventId',
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'correlationId',
            'requestId',
            'routeName',
            'ipAddress',
            'userAgent',
            'metadata',
            'occurredAt',
        ], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $viewConstructor->getParameters()));

        $repositoryConstructor = new ReflectionMethod(SecuritySignalsQueryMysqlRepository::class, '__construct');
        $this->assertCount(1, $repositoryConstructor->getParameters());
        $this->assertSame('pdo', $repositoryConstructor->getParameters()[0]->getName());
        $this->assertSame('PDO', (string) $repositoryConstructor->getParameters()[0]->getType());
    }

    public function testPrimitiveSerializationOrderingFiltersLimitAndDistinctCursorPlaceholders(): void
    {
        $query = new SecuritySignalsQueryDTO(
            after: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            before: new DateTimeImmutable('2024-01-02T00:00:00+00:00'),
            actorType: 'user',
            actorId: 123,
            signalType: 'login_failed',
            severity: 'HIGH',
            requestId: 'req',
            correlationId: 'corr',
            cursorOccurredAt: new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC')),
            cursorId: 10,
            limit: 25,
        );

        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'requestId',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_keys($query->jsonSerialize()));

        $pdo = new FakePdo();
        $repository = new SecuritySignalsQueryMysqlRepository($pdo);
        $repository->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('SELECT *', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('actor_type = :actor_type', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('actor_id = :actor_id', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))', $pdo->lastStatement->queryString);
        $this->assertStringNotContainsString(':cursor_at OR', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('ORDER BY occurred_at DESC, id DESC', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('LIMIT 25', $pdo->lastStatement->queryString);
        $this->assertSame('2024-01-01 12:00:00.123456', $pdo->lastStatement->executedParams['cursor_at_before']);
        $this->assertSame('2024-01-01 12:00:00.123456', $pdo->lastStatement->executedParams['cursor_at_equal']);
        $this->assertSame(10, $pdo->lastStatement->executedParams['cursor_id']);
    }

    public function testPrimitiveActorFiltersAreIndependentLimitClampsAndExceptionBoundaryRemains(): void
    {
        $pdo = new FakePdo();
        $repository = new SecuritySignalsQueryMysqlRepository($pdo);

        $repository->find(new SecuritySignalsQueryDTO(actorId: 123, limit: 0));
        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE actor_id = :actor_id', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('LIMIT 1', $pdo->lastStatement->queryString);

        $repository->find(new SecuritySignalsQueryDTO(actorType: 'admin'));
        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE actor_type = :actor_type', $pdo->lastStatement->queryString);

        try {
            (new SecuritySignalsQueryMysqlRepository(new ThrowingPdo()))->find(new SecuritySignalsQueryDTO());
            $this->fail('Expected storage exception.');
        } catch (SecuritySignalsStorageException $exception) {
            $this->assertStringStartsWith('Failed to query SecuritySignals records: ', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    public function testSupersededSecuritySignalsWrapperArtifactsAreAbsent(): void
    {
        $this->assertFalse(class_exists('Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryCursorDTO'));
        $this->assertFalse(class_exists('Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryPageDTO'));
        $this->assertFalse(interface_exists('Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsPaginatedQueryInterface'));
        $this->assertFalse(class_exists('Maatify\EventLogging\SecuritySignals\Service\SecuritySignalsPaginatedQueryService'));
    }
}
