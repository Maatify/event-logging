<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql;

use Closure;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest extends TestCase
{
    public function testPdoExceptionIsWrappedInStorageException(): void
    {
        $originalException = new PDOException('DB Error');
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException($originalException);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame('Failed to query AuthoritativeAudit records: DB Error', $exception->getMessage());
            self::assertSame($originalException, $exception->getPrevious());
        }
    }

    public function testMapperThrowableIsWrappedInStorageException(): void
    {
        $originalException = new RuntimeException('Mapper exploded');
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->createMock(PDO::class));

        self::setRowMapperCallable(
            $repository,
            static function () use ($originalException): never {
                throw $originalException;
            }
        );
        self::setPaginateExecutionCallable($repository, self::invokeMapper(...));

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame('Failed to map AuthoritativeAudit row: Mapper exploded', $exception->getMessage());
            self::assertSame($originalException, $exception->getPrevious());
        }
    }

    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $originalException = new InvalidPaginationQueryException('Injected configuration error');
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->createMock(PDO::class));

        self::setPaginateExecutionCallable(
            $repository,
            static function () use ($originalException): never {
                throw $originalException;
            }
        );

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditAdminQueryExecutionException was not thrown.');
        } catch (AuthoritativeAuditAdminQueryExecutionException $exception) {
            self::assertSame($originalException, $exception->getPrevious());
        }
    }

    public function testPaginationExecutionExceptionIsWrappedInStorageException(): void
    {
        $originalException = new PaginationExecutionException('Injected pagination execution error');
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->createMock(PDO::class));

        self::setPaginateExecutionCallable(
            $repository,
            static function () use ($originalException): never {
                throw $originalException;
            }
        );

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame(
                'Failed to query AuthoritativeAudit records: Injected pagination execution error',
                $exception->getMessage()
            );
            self::assertSame($originalException, $exception->getPrevious());
        }
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $originalException = new AuthoritativeAuditStorageException('Original storage exception');
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->createMock(PDO::class));

        self::setRowMapperCallable(
            $repository,
            static function () use ($originalException): never {
                throw $originalException;
            }
        );
        self::setPaginateExecutionCallable($repository, self::invokeMapper(...));

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame($originalException, $exception);
        }
    }

    /**
     * @param callable(array<string, mixed>): AuthoritativeAuditViewDTO $mapper
     * @return PageResult<AuthoritativeAuditViewDTO>
     */
    private static function invokeMapper(
        PDO $pdo,
        PdoPaginationQueryDescriptor $query,
        PageRequest $pageRequest,
        PaginationConfig $config,
        callable $mapper
    ): PageResult {
        unset($pdo, $query, $pageRequest, $config);

        $mapper([
            'event_id' => 'event-1',
            'occurred_at' => '2024-01-01 00:00:00.000000',
        ]);

        throw new RuntimeException('Injected mapper did not throw.');
    }

    private static function setPaginateExecutionCallable(
        AuthoritativeAuditAdminQueryMysqlRepository $repository,
        Closure $callable
    ): void {
        $reflection = new ReflectionClass(AuthoritativeAuditAdminQueryMysqlRepository::class);
        $property = $reflection->getProperty('paginateExecutionCallable');
        $property->setValue($repository, $callable);
    }

    private static function setRowMapperCallable(
        AuthoritativeAuditAdminQueryMysqlRepository $repository,
        Closure $callable
    ): void {
        $reflection = new ReflectionClass(AuthoritativeAuditAdminQueryMysqlRepository::class);
        $property = $reflection->getProperty('rowMapperCallable');
        $property->setValue($repository, $callable);
    }
}
