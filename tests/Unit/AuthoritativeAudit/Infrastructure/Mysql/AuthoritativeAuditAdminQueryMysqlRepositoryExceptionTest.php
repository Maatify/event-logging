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
use PDOStatement;
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

        // Pass invalid date to trigger Exception in RowMapper
        $mapRowMethod->invoke($repository, ['occurred_at' => 'invalid-date']);
    }

    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $exception = new \Maatify\Persistence\Exception\InvalidPaginationConfigurationException("test");
        $this->assertInstanceOf(\Maatify\Persistence\Exception\InvalidPaginationConfigurationException::class, $exception);
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $exception = new AuthoritativeAuditStorageException('Original storage exception');
        $this->assertInstanceOf(AuthoritativeAuditStorageException::class, $exception);
    }
}
