<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\DiagnosticsTelemetry\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryQueryInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryCursorDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryQueryDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\FakeStatement;
use Maatify\EventLogging\Tests\Support\ThrowingPdo;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class DiagnosticsTelemetryQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testPrimitiveInterfaceAndRepositoryConstructorRemainUnchanged(): void
    {
        $find = new ReflectionMethod(DiagnosticsTelemetryQueryInterface::class, 'find');
        $this->assertSame('find', $find->getName());
        $this->assertSame('array', (string) $find->getReturnType());
        $this->assertSame(DiagnosticsTelemetryQueryDTO::class, (string) $find->getParameters()[0]->getType());

        $read = new ReflectionMethod(DiagnosticsTelemetryQueryInterface::class, 'read');
        $this->assertSame('read', $read->getName());
        $this->assertSame('iterable', (string) $read->getReturnType());
        $cursorType = $read->getParameters()[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $cursorType);
        $this->assertSame(DiagnosticsTelemetryCursorDTO::class, $cursorType->getName());
        $this->assertTrue($read->getParameters()[0]->allowsNull());
        $this->assertSame(100, $read->getParameters()[1]->getDefaultValue());

        $constructor = new ReflectionMethod(DiagnosticsTelemetryQueryMysqlRepository::class, '__construct');
        $this->assertSame(['pdo', 'policy'], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));
        $this->assertSame('PDO', (string) $constructor->getParameters()[0]->getType());
        $this->assertTrue($constructor->getParameters()[1]->allowsNull());
        $this->assertTrue($constructor->getParameters()[1]->isDefaultValueAvailable());
        $this->assertNull($constructor->getParameters()[1]->getDefaultValue());
    }

    public function testPrimitiveQueryDtoConstructorAndCursorFieldsRemainUnchanged(): void
    {
        $constructor = new ReflectionMethod(DiagnosticsTelemetryQueryDTO::class, '__construct');
        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'eventKey',
            'severity',
            'requestId',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));

        $query = new DiagnosticsTelemetryQueryDTO();
        $this->assertNull($query->cursorOccurredAt);
        $this->assertNull($query->cursorId);
        $this->assertSame(50, $query->limit);
    }

    public function testFindUsesDescendingOrderingLimitAndDistinctCursorPlaceholders(): void
    {
        $pdo = new FakePdo();
        $repository = new DiagnosticsTelemetryQueryMysqlRepository($pdo);
        $cursorAt = new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'));

        $repository->find(new DiagnosticsTelemetryQueryDTO(cursorOccurredAt: $cursorAt, cursorId: 10, limit: 25));

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
        $repository = new DiagnosticsTelemetryQueryMysqlRepository($pdo);
        iterator_to_array($repository->read(new DiagnosticsTelemetryCursorDTO(
            lastOccurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            lastId: 99,
        ), 15));

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('ORDER BY occurred_at ASC, id ASC LIMIT :limit', $pdo->lastStatement->queryString);
        $this->assertSame(15, $pdo->lastStatement->boundValues[':limit']);
        $this->assertSame(99, $pdo->lastStatement->boundValues[':last_id']);
        $this->assertInstanceOf(\Throwable::class, new DiagnosticsTelemetryStorageException());
    }

    public function testPrimitiveRepositoryStillPassesCustomPolicyToFindHydration(): void
    {
        $statement = $this->statementWithRows([
            $this->row(['actor_type' => 'host-defined']),
        ]);
        $repository = new DiagnosticsTelemetryQueryMysqlRepository(
            $this->pdoReturning($statement),
            $this->customPolicy('CUSTOM_POLICY'),
        );

        $items = $repository->find(new DiagnosticsTelemetryQueryDTO(limit: 1));

        $this->assertSame('CUSTOM_POLICY', $items[0]->context->actorType->value());
    }

    public function testPrimitiveRepositoryHydratesMetadataAndTimestampsThroughFind(): void
    {
        $statement = $this->statementWithRows([
            $this->row([
                'event_id' => 'valid-metadata',
                'metadata' => '{"ok":true,"count":2}',
                'occurred_at' => '2024-01-01 12:34:56.123456',
            ]),
            $this->row([
                'event_id' => 'fallbacks',
                'metadata' => '{bad',
                'occurred_at' => [],
            ]),
        ]);
        $repository = new DiagnosticsTelemetryQueryMysqlRepository($this->pdoReturning($statement));

        $items = $repository->find(new DiagnosticsTelemetryQueryDTO(limit: 2));

        $this->assertSame(['ok' => true, 'count' => 2], $items[0]->metadata);
        $this->assertSame('2024-01-01 12:34:56.123456', $items[0]->context->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $items[0]->context->occurredAt->getTimezone()->getName());
        $this->assertNull($items[1]->metadata);
        $this->assertSame('1970-01-01 00:00:00.000000', $items[1]->context->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $items[1]->context->occurredAt->getTimezone()->getName());
    }

    public function testPrimitiveFindExceptionMessagesAndPreviousThrowablesRemainStable(): void
    {
        try {
            (new DiagnosticsTelemetryQueryMysqlRepository(new ThrowingPdo()))->find(new DiagnosticsTelemetryQueryDTO());
            $this->fail('Expected query storage exception.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            $this->assertSame(
                'Failed to query DiagnosticsTelemetry records: Simulated PDO connection/prepare error',
                $exception->getMessage(),
            );
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
        }

        $repository = new DiagnosticsTelemetryQueryMysqlRepository(
            $this->pdoReturning($this->statementWithRows([$this->row()])),
            $this->throwingPolicy(),
        );

        try {
            $repository->find(new DiagnosticsTelemetryQueryDTO());
            $this->fail('Expected mapper storage exception.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            $this->assertSame(
                'Failed to map DiagnosticsTelemetry row: policy failed',
                $exception->getMessage(),
            );
            $this->assertInstanceOf(Exception::class, $exception->getPrevious());
            $this->assertSame('policy failed', $exception->getPrevious()->getMessage());
        }
    }

    public function testPrimitiveReadExceptionMessagesAndPreviousThrowablesRemainStable(): void
    {
        try {
            iterator_to_array((new DiagnosticsTelemetryQueryMysqlRepository(new ThrowingPdo()))->read(null));
            $this->fail('Expected read storage exception.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            $this->assertSame(
                'Failed to read telemetry logs: Simulated PDO connection/prepare error',
                $exception->getMessage(),
            );
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
        }

        $repository = new DiagnosticsTelemetryQueryMysqlRepository(
            $this->pdoReturning($this->statementWithRows([$this->row()])),
            $this->throwingPolicy(),
        );

        try {
            iterator_to_array($repository->read(null));
            $this->fail('Expected read mapper storage exception.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            $this->assertSame(
                'Failed to map telemetry row: policy failed',
                $exception->getMessage(),
            );
            $this->assertInstanceOf(Exception::class, $exception->getPrevious());
            $this->assertSame('policy failed', $exception->getPrevious()->getMessage());
        }
    }

    public function testOutOfScopeAdminQuerySurfaceIsStillAbsentButApprovedDomainSurfaceExists(): void
    {
        $this->assertFalse(interface_exists('Maatify\EventLogging\Contract\AdminQueryInterface'));
        $this->assertFalse(class_exists('Maatify\EventLogging\Http\DiagnosticsTelemetryAdminQueryController'));
        $this->assertTrue(interface_exists('Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryAdminQueryInterface'));
        $this->assertTrue(class_exists('Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryAdminQueryMysqlRepository'));

        $schemaPath = __DIR__ . '/../../../../../src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql';
        $this->assertFileExists($schemaPath);
        $this->assertStringContainsString(
            'CREATE TABLE maa_event_logging_diagnostics_telemetry',
            (string) file_get_contents($schemaPath),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'id' => 1,
            'event_id' => 'event-1',
            'event_key' => 'system_start',
            'severity' => 'INFO',
            'actor_type' => 'SYS',
            'actor_id' => 10,
            'metadata' => '{}',
            'correlation_id' => 'corr',
            'request_id' => 'req',
            'route_name' => 'route',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'agent',
            'duration_ms' => 50,
            'occurred_at' => '2024-01-01 00:00:00.000000',
        ], $overrides);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function statementWithRows(array $rows): FakeStatement
    {
        $statement = new FakeStatement();
        $statement->fetchResults = $rows;

        return $statement;
    }

    private function pdoReturning(FakeStatement $statement): PDO
    {
        return new class($statement) extends PDO {
            public function __construct(private FakeStatement $statement)
            {
            }

            /**
             * @param array<int, mixed> $options
             */
            public function prepare(string $query, array $options = []): PDOStatement
            {
                $this->statement->queryString = $query;
                return $this->statement;
            }
        };
    }

    private function customPolicy(string $actorType): DiagnosticsTelemetryPolicyInterface
    {
        return new class($actorType) implements DiagnosticsTelemetryPolicyInterface {
            public function __construct(private string $actorType)
            {
            }

            public function normalizeActorType(string|DiagnosticsTelemetryActorTypeInterface $actorType): DiagnosticsTelemetryActorTypeInterface
            {
                return new class($this->actorType) implements DiagnosticsTelemetryActorTypeInterface {
                    public function __construct(private string $actorType)
                    {
                    }

                    public function value(): string
                    {
                        return $this->actorType;
                    }
                };
            }

            public function normalizeSeverity(string|DiagnosticsTelemetrySeverityInterface $severity): DiagnosticsTelemetrySeverityInterface
            {
                return new class() implements DiagnosticsTelemetrySeverityInterface {
                    public function value(): string
                    {
                        return 'INFO';
                    }
                };
            }

            public function validateMetadataSize(string $json): bool
            {
                return true;
            }
        };
    }

    private function throwingPolicy(): DiagnosticsTelemetryPolicyInterface
    {
        return new class implements DiagnosticsTelemetryPolicyInterface {
            public function normalizeActorType(string|DiagnosticsTelemetryActorTypeInterface $actorType): DiagnosticsTelemetryActorTypeInterface
            {
                throw new Exception('policy failed');
            }

            public function normalizeSeverity(string|DiagnosticsTelemetrySeverityInterface $severity): DiagnosticsTelemetrySeverityInterface
            {
                throw new Exception('policy failed');
            }

            public function validateMetadataSize(string $json): bool
            {
                return true;
            }
        };
    }
}
