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
use ReflectionParameter;
use ReflectionProperty;

final class AuthoritativeAuditQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testPrimitiveInterfaceContractIsPreserved(): void
    {
        $method = new ReflectionMethod(AuthoritativeAuditQueryInterface::class, 'find');
        $parameters = $method->getParameters();

        self::assertSame(['query'], self::parameterNames($parameters));
        self::assertSame([AuthoritativeAuditQueryDTO::class], self::parameterTypes($parameters));

        $returnType = $method->getReturnType();
        if ($returnType === null) {
            self::fail('Primitive find() return type is missing.');
        }
        self::assertSame('array', (string) $returnType);

        $docComment = $method->getDocComment();
        if (!is_string($docComment)) {
            self::fail('Primitive find() docblock is missing.');
        }
        self::assertStringContainsString('@return array<AuthoritativeAuditViewDTO>', $docComment);
        self::assertStringContainsString('@throws AuthoritativeAuditStorageException', $docComment);
    }

    public function testPrimitiveQueryDtoConstructorAndSerializationArePreserved(): void
    {
        $constructor = (new ReflectionClass(AuthoritativeAuditQueryDTO::class))->getConstructor();
        if (!$constructor instanceof ReflectionMethod) {
            self::fail('AuthoritativeAuditQueryDTO constructor is missing.');
        }
        $parameters = $constructor->getParameters();

        self::assertSame([
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
        ], self::parameterNames($parameters));
        self::assertSame([
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
        ], self::parameterTypes($parameters));
        self::assertSame([
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
        ], self::parameterDefaults($parameters));

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
        if (!is_array($serialized)) {
            self::fail('AuthoritativeAuditQueryDTO serialization must return an array.');
        }
        self::assertSame([
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

    public function testPrimitiveViewDtoConstructorAndSerializationArePreserved(): void
    {
        $constructor = (new ReflectionClass(AuthoritativeAuditViewDTO::class))->getConstructor();
        if (!$constructor instanceof ReflectionMethod) {
            self::fail('AuthoritativeAuditViewDTO constructor is missing.');
        }
        $parameters = $constructor->getParameters();

        self::assertSame([
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
        ], self::parameterNames($parameters));
        self::assertSame([
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
        ], self::parameterTypes($parameters));

        foreach ($parameters as $parameter) {
            self::assertFalse($parameter->isDefaultValueAvailable());
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
        if (!is_array($serialized)) {
            self::fail('AuthoritativeAuditViewDTO serialization must return an array.');
        }
        self::assertSame([
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

    public function testPrimitiveRepositoryConstructorDefaultSqlAndLimitArePreserved(): void
    {
        $constructor = new ReflectionMethod(AuthoritativeAuditQueryMysqlRepository::class, '__construct');
        self::assertSame(['pdo'], self::parameterNames($constructor->getParameters()));
        self::assertSame([PDO::class], self::parameterTypes($constructor->getParameters()));

        $property = new ReflectionProperty(AuthoritativeAuditQueryMysqlRepository::class, 'pdo');
        self::assertTrue($property->isPrivate());
        self::assertTrue($property->isReadOnly());
        self::assertSame(PDO::class, (string) $property->getType());

        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);
        $repository->find(new AuthoritativeAuditQueryDTO(limit: 0));

        $statement = $pdo->lastStatement;
        if ($statement === null) {
            self::fail('Primitive repository did not prepare a SQL statement.');
        }
        self::assertSame(
            'SELECT * FROM maa_event_logging_authoritative_audit_log  ORDER BY occurred_at DESC, id DESC LIMIT 1',
            $statement->queryString
        );
        self::assertSame([], $statement->executedParams);
    }

    public function testPrimitiveCursorRequiresBothValuesAndUsesDistinctPlaceholders(): void
    {
        $cursorAt = new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'));
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);

        $repository->find(new AuthoritativeAuditQueryDTO(cursorOccurredAt: $cursorAt));
        $statement = $pdo->lastStatement;
        if ($statement === null) {
            self::fail('Primitive cursor query was not prepared.');
        }
        self::assertStringNotContainsString(':cursor_at_before', $statement->queryString);

        $repository->find(new AuthoritativeAuditQueryDTO(cursorId: 100));
        $statement = $pdo->lastStatement;
        if ($statement === null) {
            self::fail('Primitive cursor query was not prepared.');
        }
        self::assertStringNotContainsString(':cursor_at_before', $statement->queryString);

        $repository->find(new AuthoritativeAuditQueryDTO(cursorOccurredAt: $cursorAt, cursorId: 100));
        $statement = $pdo->lastStatement;
        if ($statement === null) {
            self::fail('Primitive cursor query was not prepared.');
        }
        self::assertStringContainsString(
            '(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))',
            $statement->queryString
        );
        self::assertStringContainsString('ORDER BY occurred_at DESC, id DESC', $statement->queryString);
        self::assertSame([
            'cursor_at_before' => '2024-01-01 12:00:00.123456',
            'cursor_at_equal' => '2024-01-01 12:00:00.123456',
            'cursor_id' => 100,
        ], $statement->executedParams);
    }

    public function testAdminDescriptorConvertsCairoBoundsToUtcWithMicroseconds(): void
    {
        $builder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
        $cairo = new DateTimeZone('Africa/Cairo');
        $request = new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-06-01 11:00:00.123456', $cairo),
            before: new DateTimeImmutable('2024-06-01 12:00:00.654321', $cairo)
        );

        $descriptor = $builder->build($request);

        self::assertSame([
            'after' => '2024-06-01 08:00:00.123456',
            'before' => '2024-06-01 09:00:00.654321',
        ], $descriptor->filteredCountParams);
        self::assertSame($descriptor->filteredCountParams, $descriptor->dataParams);
    }

    public function testSupersededPostV1RuntimeTypesAreAbsent(): void
    {
        self::assertFalse(interface_exists('Maatify\\EventLogging\\AuthoritativeAudit\\Contract\\AuthoritativeAuditPaginatedQueryInterface'));
        self::assertFalse(class_exists('Maatify\\EventLogging\\AuthoritativeAudit\\Service\\AuthoritativeAuditPaginatedQueryService'));
        self::assertFalse(class_exists('Maatify\\EventLogging\\AuthoritativeAudit\\DTO\\AuthoritativeAuditQueryPageDTO'));
        self::assertFalse(class_exists('Maatify\\EventLogging\\AuthoritativeAudit\\DTO\\AuthoritativeAuditQueryCursorDTO'));
    }

    /**
     * @param list<ReflectionParameter> $parameters
     * @return list<string>
     */
    private static function parameterNames(array $parameters): array
    {
        return array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            $parameters
        );
    }

    /**
     * @param list<ReflectionParameter> $parameters
     * @return list<string>
     */
    private static function parameterTypes(array $parameters): array
    {
        return array_map(
            static function (ReflectionParameter $parameter): string {
                $type = $parameter->getType();
                if ($type === null) {
                    self::fail("Parameter {$parameter->getName()} has no type.");
                }

                return (string) $type;
            },
            $parameters
        );
    }

    /**
     * @param list<ReflectionParameter> $parameters
     * @return list<mixed>
     */
    private static function parameterDefaults(array $parameters): array
    {
        return array_map(
            static function (ReflectionParameter $parameter): mixed {
                if (!$parameter->isDefaultValueAvailable()) {
                    self::fail("Parameter {$parameter->getName()} has no default value.");
                }

                return $parameter->getDefaultValue();
            },
            $parameters
        );
    }
}
