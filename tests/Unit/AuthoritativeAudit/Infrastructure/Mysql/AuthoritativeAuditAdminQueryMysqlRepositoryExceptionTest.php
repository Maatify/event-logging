<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;
use ReflectionClass;

final class AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest extends TestCase
{
    public function testPdoExceptionIsWrappedInStorageException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException("DB Error"));

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to query AuthoritativeAudit records: DB Error');

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }

    public function testMapperThrowableIsWrappedInStorageException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
        $reflection = new ReflectionClass($repository);
        $mapRowMethod = $reflection->getMethod('mapRow');
        $mapRowMethod->setAccessible(true);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to map AuthoritativeAudit row: Invalid occurred_at format');

        $mapRowMethod->invoke($repository, ['occurred_at' => 'invalid-date']);
    }

    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $reflection = new ReflectionClass($repository);
        $paginateCallableProp = $reflection->getProperty('paginateExecutionCallable');
        $paginateCallableProp->setAccessible(true);
        $paginateCallableProp->setValue($repository, function () {
            throw new \Maatify\Persistence\Exception\InvalidPaginationQueryException('Injected configuration error');
        });

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }

    public function testPaginationExecutionExceptionIsWrappedInStorageException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $originalException = new \Maatify\Persistence\Exception\PaginationExecutionException('Injected pagination execution error');

        $reflection = new ReflectionClass($repository);
        $paginateCallableProp = $reflection->getProperty('paginateExecutionCallable');
        $paginateCallableProp->setAccessible(true);
        $paginateCallableProp->setValue($repository, function () use ($originalException) {
            throw $originalException;
        });

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to query AuthoritativeAudit records: Injected pagination execution error');

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertSame($originalException, $e->getPrevious());
            throw $e;
        }
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $originalException = new AuthoritativeAuditStorageException('Original storage exception');

        $reflection = new ReflectionClass($repository);
        $paginateCallableProp = $reflection->getProperty('paginateExecutionCallable');
        $paginateCallableProp->setAccessible(true);

        $paginateCallableProp->setValue($repository, function (\PDO $pdo, \Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor $query, \Maatify\Persistence\Pdo\Pagination\PageRequest $pageRequest, \Maatify\Persistence\Pdo\Pagination\PaginationConfig $config, callable $mapper) use ($originalException) {
            try {
                $mapper(['occurred_at' => 'invalid']);
            } catch (AuthoritativeAuditStorageException $e) {
                throw $originalException;
            }
        });

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Original storage exception');

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertSame($originalException, $e);
            throw $e;
        }
    }
}
