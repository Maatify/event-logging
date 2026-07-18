<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder;
use Maatify\EventLogging\Tests\Support\FakePdo;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AuthoritativeAuditQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testPrimitiveInterfaceContractIsPreserved(): void
    {
        $method = new ReflectionMethod(AuthoritativeAuditQueryInterface::class, 'find');
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame('query', $parameters[0]->getName());
        $this->assertSame(AuthoritativeAuditQueryDTO::class, (string) $parameters[0]->getType());
        $this->assertSame('array', (string) $method->getReturnType());

        $docComment = $method->getDocComment();
        $this->assertIsString($docComment);
        $this->assertStringContainsString('@return array<AuthoritativeAuditViewDTO>', $docComment);
        $this->assertStringContainsString('@throws AuthoritativeAuditStorageException', $docComment);
    }

    public function testPrimitiveQueryDtoConstructorAndSerializationContractIsPreserved(): void
    {
        $reflection = new ReflectionClass(AuthoritativeAuditQueryDTO::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();

        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'targetType',
            'targetId',
            'action',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_map(static fn ($parameter): string => $parameter->getName(), $parameters));
        $this->assertSame([
            '?DateTimeImmutable',
            '?DateTimeImmutable',
            '?string',
            '?int',
            '?string',
            '?int',
            '?string',
            '?string',
            '?DateTimeImmutable',
            '?int',
            'int',
        ], array_map(static fn ($parameter): string => (string) $parameter->getType(), $parameters));
        $this->assertSame([
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            50,
        ], array_map(static fn ($parameter): mixed => $parameter->getDefaultValue(), $parameters));

        $after = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $before = new DateTimeImmutable('2024-01-02 10:00:00', new DateTimeZone('UTC'));
        $cursor = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'));
        $dto = new AuthoritativeAuditQueryDTO(
            after: $after,
            before: $before,
            actorType: 'admin',
            actorId: 10,
            targetType: 'user',
            targetId: 20,
            action: 'update',
            correlationId: 'corr-1',
            cursorOccurredAt: $cursor,
            cursorId: 30,
            limit: 40
        );

        $serialized = $dto->jsonSerialize();
        $this->assertIsArray($serialized);
        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'targetType',
            'targetId',
            'action',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_keys($serialized));
        $this->assertSame([
            'after' => $after->format(DATE_ATOM),
            'before' => $before->format(DATE_ATOM),
            'actorType' => 'admin',
            'actorId' => 10,
            'targetType' => 'user',
            'targetId' => 20,
            'action' => 'update',
            'correlationId' => 'corr-1',
            'cursorOccurredAt' => $cursor->format(DATE_ATOM),
            'cursorId' => 30,
            'limit' => 40,
        ], $serialized);
    }

    public function testPrimitiveViewDtoConstructorAndSerializationContractIsPreserved(): void
    {
        $reflection = new ReflectionClass(AuthoritativeAuditViewDTO::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();

        $this->assertSame([
            'id',
            'eventId',
            'actorType',
            'actorId',
            'action',
            'targetType',
            'targetId',
            'ipAddress',
            'userAgent',
            'correlationId',
            'changes',
            'occurredAt',
        ], array_map(static fn ($parameter): string => $parameter->getName(), $parameters));
        $this->assertSame([
            'int',
            'string',
            '?string',
            '?int',
            'string',
            '?string',
            '?int',
            '?string',
            '?string',
            '?string',
            '?array',
            'DateTimeImmutable',
        ], array_map(static fn ($parameter): string => (string) $parameter->getType(), $parameters));
        foreach ($parameters as $parameter) {
            $this->assertFalse($parameter->isDefaultValueAvailable());
        }

        $occurredAt = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'));
        $dto = new AuthoritativeAuditViewDTO(
            id: 1,
            eventId: 'event-1',
            actorType: 'admin',
            actorId: 2,
            action: 'update',
            targetType: 'user',
            targetId: 3,
            ipAddress: '127.0.0.1',
            userAgent: 'test-agent',
            correlationId: 'corr-1',
            changes: ['old' => 'a', 'new' => 'b'],
            occurredAt: $occurredAt
        );

        $serialized = $dto->jsonSerialize();
        $this->assertIsArray($serialized);
        $this->assertSame([
            'id',
            'eventId',
            'actorType',
            'actorId',
            'action',
            'targetType',
            'targetId',
            'ipAddress',
            'userAgent',
            'correlationId',
            'changes',
            'occurredAt',
        ], array_keys($serialized));
        $this->assertSame([
            'id' => 1,
            'eventId' => 'event-1',
            'actorType' => 'admin',
            'actorId' => 2,
            'action' => 'update',
            'targetType' => 'user',
            'targetId' => 3,
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'test-agent',
            'correlationId' => 'corr-1',
            'changes' => ['old' => 'a', 'new' => 'b'],
            'occurredAt' => $occurredAt->format(DATE_ATOM),
        ], $serialized);
    }

    public function testPrimitiveRepositoryConstructorAndDefaultSqlContractArePreserved(): void
    {
        $constructor = new ReflectionMethod(AuthoritativeAuditQueryMysqlRepository::class, '__construct');
        $parameters = $constructor->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame('pdo', $parameters[0]->getName());
        $this->assertSame(PDO::class, (string) $parameters[0]->getType());

        $property = new ReflectionProperty(AuthoritativeAuditQueryMysqlRepository::class, 'pdo');
        $this->assertTrue($property->isPrivate());
        $this->assertTrue($property->isReadOnly());
        $this->assertSame(PDO::class, (string) $property->getType());

        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);
        $repository->find(new AuthoritativeAuditQueryDTO(limit: 0));

        $this->assertNotNull($pdo->lastStatement);
        $this->assertSame(
            'SELECT * FROM maa_event_logging_authoritative_audit_log  ORDER BY occurred_at DESC, id DESC LIMIT 1',
            $pdo->lastStatement->queryString
        );
        $this->assertSame([], $pdo->lastStatement->executedParams);
    }

    public function testPrimitiveCursorActivatesOnlyWhenBothValuesArePresent(): void
    {
        $cursorAt = new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'));
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $repository->find(new AuthoritativeAuditQueryDTO(cursorOccurredAt: $cursorAt));
        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringNotContainsString(':cursor_at_before', $pdo->lastStatement->queryString);
        $this->assertSame([], $pdo->lastStatement->executedParams);

        $repository->find(new AuthoritativeAuditQueryDTO(cursorId: 100));
        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringNotContainsString(':cursor_at_before', $pdo->lastStatement->queryString);
        $this->assertSame([], $pdo->lastStatement->executedParams);

        $repository->find(new AuthoritativeAuditQueryDTO(cursorOccurredAt: $cursorAt, cursorId: 100));
        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString(
            '(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))',
            $pdo->lastStatement->queryString
        );
        $this->assertSame([
            'cursor_at_before' => '2024-01-01 12:00:00.123456',
            'cursor_at_equal' => '2024-01-01 12:00:00.123456',
            'cursor_id' => 100,
        ], $pdo->lastStatement->executedParams);
    }

    public function testAdminDescriptorConvertsAfricaCairoBoundsToUtcWithMicroseconds(): void
    {
        $builder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
        $cairo = new DateTimeZone('Africa/Cairo');
        $request = new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-06-01 11:00:00.123456', $cairo),
            before: new DateTimeImmutable('2024-06-01 12:00:00.654321', $cairo)
        );

        $descriptor = $builder->build($request);

        $this->assertSame([
            'after' => '2024-06-01 08:00:00.123456',
            'before' => '2024-06-01 09:00:00.654321',
        ], $descriptor->filteredCountParams);
        $this->assertSame($descriptor->filteredCountParams, $descriptor->dataParams);
        $this->assertStringContainsString('occurred_at >= :after', $descriptor->filteredCountSql);
        $this->assertStringContainsString('occurred_at <= :before', $descriptor->filteredCountSql);
    }
}
