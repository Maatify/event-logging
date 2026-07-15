<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Infrastructure\Mysql;

use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminPageResultDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryExecutionException;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SecuritySignalsAdminQueryMysqlRepositoryTest extends TestCase
{
    public function testConstructorIsPdoOnly(): void
    {
        $constructor = new ReflectionMethod(SecuritySignalsAdminQueryMysqlRepository::class, '__construct');

        $this->assertCount(1, $constructor->getParameters());
        $this->assertSame('pdo', $constructor->getParameters()[0]->getName());
        $this->assertSame('PDO', (string) $constructor->getParameters()[0]->getType());
    }

    public function testPageRequestPaginatorResultAndResultDtoMapping(): void
    {
        $dataStatement = new SecuritySignalsAdminQueryDataStatement([
            [
                'id' => '5',
                'event_id' => 'evt-5',
                'actor_type' => 'user',
                'actor_id' => '10',
                'signal_type' => 'login_failed',
                'severity' => 'HIGH',
                'correlation_id' => 'corr',
                'request_id' => 'req',
                'route_name' => 'route',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'agent',
                'metadata' => '{"ok":true}',
                'occurred_at' => '2024-01-01 12:00:00.000000',
            ],
        ]);
        $pdo = new SecuritySignalsAdminQueryPagedPdo([
            new SecuritySignalsAdminQueryCountStatement(3),
            new SecuritySignalsAdminQueryCountStatement(3),
            $dataStatement,
        ]);

        $repository = new SecuritySignalsAdminQueryMysqlRepository($pdo);
        $result = $repository->paginate(new SecuritySignalsAdminQueryRequestDTO(
            page: 2,
            perPage: 1,
            sortBy: 'occurred_at',
            sortDirection: 'ASC',
        ));

        $this->assertInstanceOf(SecuritySignalsAdminPageResultDTO::class, $result);
        $this->assertSame(2, $result->page);
        $this->assertSame(1, $result->perPage);
        $this->assertSame(3, $result->total);
        $this->assertSame(3, $result->filtered);
        $this->assertSame(3, $result->totalPages);
        $this->assertTrue($result->hasNext);
        $this->assertTrue($result->hasPrevious);
        $this->assertSame('occurred_at', $result->sortBy);
        $this->assertSame('ASC', $result->sortDirection);
        $this->assertCount(1, $result->items);
        $this->assertSame('evt-5', $result->items[0]->eventId);
        $this->assertSame(['ok' => true], $result->items[0]->metadata);
        $this->assertStringContainsString('ORDER BY `occurred_at` ASC, `id` DESC', $dataStatement->queryString);
        $this->assertSame(1, $dataStatement->boundValues[':__pagination_limit']);
        $this->assertSame(1, $dataStatement->boundValues[':__pagination_offset']);
    }

    public function testRepositoryDoesNotOwnTransactions(): void
    {
        $pdo = new SecuritySignalsAdminQueryPagedPdo([
            new SecuritySignalsAdminQueryCountStatement(0),
            new SecuritySignalsAdminQueryCountStatement(0),
            new SecuritySignalsAdminQueryCountStatement(0),
            new SecuritySignalsAdminQueryCountStatement(0),
        ]);
        $repository = new SecuritySignalsAdminQueryMysqlRepository($pdo);

        $this->assertFalse($pdo->inTransaction());
        $repository->paginate(new SecuritySignalsAdminQueryRequestDTO());
        $this->assertFalse($pdo->inTransaction());

        $pdo->beginTransaction();
        $repository->paginate(new SecuritySignalsAdminQueryRequestDTO());
        $this->assertTrue($pdo->inTransaction());
        $pdo->rollBack();
    }

    public function testPdoExceptionIsTranslatedToStorageExceptionWithPreviousAndExactPrefix(): void
    {
        $pdo = $this->createMock(PDO::class);
        $previous = new PDOException('database down');
        $pdo->method('prepare')->willThrowException($previous);

        $repository = new SecuritySignalsAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new SecuritySignalsAdminQueryRequestDTO());
            $this->fail('Expected storage exception.');
        } catch (SecuritySignalsStorageException $exception) {
            $this->assertSame('Failed to query SecuritySignals records: database down', $exception->getMessage());
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function testPaginationExecutionExceptionIsTranslatedToStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn(false);

        $repository = new SecuritySignalsAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new SecuritySignalsAdminQueryRequestDTO());
            self::fail('Expected SecuritySignalsStorageException.');
        } catch (SecuritySignalsStorageException $exception) {
            self::assertSame(
                'Failed to query SecuritySignals records: Failed to prepare pagination query.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(PaginationExecutionException::class, $exception->getPrevious());
        }
    }

    public function testInvalidPaginationQueryExceptionBoundaryFactory(): void
    {
        $previous = new InvalidPaginationQueryException('bad pagination descriptor');
        $exception = SecuritySignalsAdminQueryExecutionException::executionFailed($previous);

        $this->assertSame('SecuritySignals Admin Query execution failed: bad pagination descriptor', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testPersistenceDescriptorRejectsInvalidQueryAtBoundary(): void
    {
        $this->expectException(InvalidPaginationQueryException::class);
        $this->expectExceptionMessage('Invalid pagination parameter key.');

        new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM maa_event_logging_security_signals',
            totalParams: [':bad' => 'value'],
            filteredCountSql: 'SELECT COUNT(*) FROM maa_event_logging_security_signals',
            filteredCountParams: [],
            dataSql: 'SELECT id FROM maa_event_logging_security_signals',
            dataParams: [],
        );
    }

    public function testRowMappingFailureUsesExactPrefixAndPreservesPreviousThrowable(): void
    {
        $repository = new SecuritySignalsAdminQueryMysqlRepository(new SecuritySignalsAdminQueryPagedPdo([]));
        $method = new ReflectionMethod(SecuritySignalsAdminQueryMysqlRepository::class, 'mapRow');

        try {
            $method->invoke($repository, ['occurred_at' => 'not a date']);
            $this->fail('Expected storage exception.');
        } catch (SecuritySignalsStorageException $exception) {
            $this->assertStringStartsWith('Failed to map SecuritySignals row: ', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }
}

final class SecuritySignalsAdminQueryPagedPdo extends PDO
{
    /** @param list<PDOStatement|false> $statements */
    public function __construct(private array $statements)
    {
    }

    /** @param array<int, mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $statement = array_shift($this->statements);
        if ($statement instanceof PDOStatement) {
            $statement->queryString = $query;
        }

        return $statement ?? false;
    }

    public function beginTransaction(): bool
    {
        $this->inTransaction = true;
        return true;
    }

    public function rollBack(): bool
    {
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    private bool $inTransaction = false;
}

final class SecuritySignalsAdminQueryCountStatement extends PDOStatement
{
    /** @var list<array<string, mixed>> */
    private array $rows;

    public function __construct(int $count)
    {
        $this->rows = [['count' => (string) $count]];
    }

    /** @param array<string|int, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function columnCount(): int
    {
        return 1;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): mixed
    {
        return array_shift($this->rows) ?: false;
    }

    public function errorCode(): string
    {
        return '00000';
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }
}

final class SecuritySignalsAdminQueryDataStatement extends PDOStatement
{
    /** @var array<string|int, mixed> */
    public array $boundValues = [];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(private array $rows)
    {
    }

    /** @param array<string|int, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): mixed
    {
        return array_shift($this->rows) ?: false;
    }

    public function errorCode(): string
    {
        return '00000';
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundValues[$param] = $value;
        return true;
    }
}
