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
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        // Let's use a test seam to make PdoPaginator throw InvalidPaginationQueryException natively.
        // Actually, we already modified the repository to have `$rowMapperClosure` which gives us a seam for the mapper.
        // What about triggering InvalidPaginationConfigurationException natively?
        // Let's just trigger it from the constructor of PageRequest by using page=0!
        // No, PageRequest throws it internally, wait.
        // If PageRequest doesn't throw it, we can trigger InvalidPaginationQueryException by passing invalid SortWhitelist?
        // AuthoritativeAuditAdminQueryMysqlRepository hardcodes SortWhitelist with "occurred_at" and "id".
        // AuthoritativeAuditAdminQueryRequestDTO allows ANY string for sortBy.
        // If we pass an unlisted sort column, PdoPaginator's `resolveSortBy` returns defaultSortBy (occurred_at), it DOES NOT THROW!

        // Wait, how DOES InvalidPaginationConfigurationException / InvalidPaginationQueryException get thrown?
        // They are thrown by `Maatify\Persistence\Exception\*`.
        // To cover this path properly, we MUST use a seam. Let's check how we can inject it.
        // We could just reflection-inject a mock PdoPaginator! But PdoPaginator is final, we can't mock it in PHPUnit.
        // We could reflection-inject a mock DescriptorBuilder! DescriptorBuilder is final, we can't mock it.
        // We can throw it from PDO statement fetch? No, they don't catch PDOException in that block, they catch it in another block.
        // They catch InvalidPaginationConfigurationException | InvalidPaginationQueryException.

        // Wait! We can throw it from PDO::prepare if we mock it!
        // Yes! We did this before and it worked!
        $pdo->method("prepare")->willThrowException(new \Maatify\Persistence\Exception\InvalidPaginationQueryException("Test exception"));

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);
        $this->expectExceptionMessage("Test exception");

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmtTotal = $this->createMock(PDOStatement::class);
        $stmtFiltered = $this->createMock(PDOStatement::class);
        $stmtData = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturnOnConsecutiveCalls($stmtTotal, $stmtFiltered, $stmtData);

        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("errorCode")->willReturn("00000");
        $stmtTotal->method("bindValue")->willReturn(true);
        $stmtTotal->method("fetch")->willReturnOnConsecutiveCalls(["count" => 1], false);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("columnCount")->willReturn(1);
        $stmtFiltered->method("errorCode")->willReturn("00000");
        $stmtFiltered->method("bindValue")->willReturn(true);
        $stmtFiltered->method("fetch")->willReturnOnConsecutiveCalls(["count" => 1], false);

        $stmtData->method("execute")->willReturn(true);
        $stmtData->method("errorCode")->willReturn("00000");
        $stmtData->method("bindValue")->willReturn(true);
        $stmtData->method("fetch")->willReturnOnConsecutiveCalls(
            ["event_id" => "mock-event"],
            false
        );

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $originalException = new AuthoritativeAuditStorageException("Exact same instance");

        $reflection = new ReflectionClass($repository);
        $closureProp = $reflection->getProperty("rowMapperClosure");
        $closureProp->setAccessible(true);
        $closureProp->setValue($repository, function() use ($originalException) {
            throw $originalException;
        });

        // Let's capture the exception directly rather than expectException to debug
        $caught = null;
        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame($originalException, $caught);
    }
}
