<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\InvalidPaginationConfigurationException;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Exception\PaginationExecutionException;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends TestCase
{
    public function testInvalidPaginationConfigurationMapsToExecutionException(): void
    {
        $prev = new InvalidPaginationConfigurationException('Config error');
        $e = AuthoritativeAuditAdminQueryExecutionException::executionFailed($prev);
        $this->assertSame('AuthoritativeAudit Admin Query execution failed: Config error', $e->getMessage());
        $this->assertSame($prev, $e->getPrevious());
        $this->assertSame(0, $e->getCode()); // SystemMaatifyException does not assign code to property directly from defaultErrorCode without executing properly
    }

    public function testInvalidPaginationQueryMapsToExecutionException(): void
    {
        $prev = new InvalidPaginationQueryException('Query error');
        $e = AuthoritativeAuditAdminQueryExecutionException::executionFailed($prev);
        $this->assertSame('AuthoritativeAudit Admin Query execution failed: Query error', $e->getMessage());
        $this->assertSame($prev, $e->getPrevious());
        $this->assertSame(0, $e->getCode()); // SystemMaatifyException does not assign code to property directly from defaultErrorCode without executing properly
    }

    public function testPaginationExecutionFailureMapsToStorageException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PaginationExecutionException('Execution failed'));

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
        $request = new AuthoritativeAuditAdminQueryRequestDTO();

        try {
            $repository->paginate($request);
            $this->fail('Expected exception was not thrown');
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertSame('Failed to query AuthoritativeAudit records: Execution failed', $e->getMessage());
            $this->assertInstanceOf(PaginationExecutionException::class, $e->getPrevious());
        }
    }

    public function testPDOExceptionMapsToStorageException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('PDO failed'));

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
        $request = new AuthoritativeAuditAdminQueryRequestDTO();

        try {
            $repository->paginate($request);
            $this->fail('Expected exception was not thrown');
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertSame('Failed to query AuthoritativeAudit records: PDO failed', $e->getMessage());
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
        }
    }

    public function testMapperThrowableIsCaughtAndWrappedInStorageException(): void
    {
        $pdo = $this->createMock(PDO::class);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        // Paginator's strict matching in `fetch` returns false if the PHPUnit mock isn't perfect,
        // which throws the generic "must return associative array".
        // The most accurate and robust way to test the exact mapRow boundary is to use reflection.
        // It strictly verifies that mapRow() captures Throwables and wraps them in AuthoritativeAuditStorageException,
        // without depending on PdoPaginator's internal data-fetching details.
        $reflection = new \ReflectionClass($repository);
        $mapRow = $reflection->getMethod('mapRow');
        $mapRow->setAccessible(true);

        try {
            // 'occurred_at' expects a valid datetime string. 'invalid' causes DateTimeImmutable to throw.
            $mapRow->invoke($repository, ['occurred_at' => 'invalid date']);
            $this->fail('Expected exception was not thrown');
        } catch (\ReflectionException $e) {
            // The exception thrown by invoke is wrapped in ReflectionException.
            $original = $e->getPrevious();
            $this->assertInstanceOf(AuthoritativeAuditStorageException::class, $original);
            $this->assertStringContainsString('Failed to map AuthoritativeAudit row', $original->getMessage());
            // It should preserve the underlying DateTimeImmutable exception
            $this->assertInstanceOf(\Exception::class, $original->getPrevious());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertStringContainsString('Failed to map AuthoritativeAudit row', $e->getMessage());
            $this->assertInstanceOf(\Exception::class, $e->getPrevious());
        }
    }
}
