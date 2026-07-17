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
        $stmtTotal = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturn($stmtTotal);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // DescriptorBuilder is also final!
        // We can just throw it from PageRequest natively.
        // Wait, how does PageRequest throw InvalidPaginationConfigurationException?
        // Let's check PageRequest again. It doesn't, it just sets page = 1 if page < 1 in PdoPaginator!
        // Wait, I saw it throw something earlier...
        // Ah, PdoPaginator normalizeCountValue throws PaginationExecutionException.
        // What throws InvalidPaginationConfigurationException natively?
        // SortWhitelist constructor? If we create a bad SortWhitelist. But we can't.
        // What throws InvalidPaginationQueryException natively?
        // DescriptorBuilder throws InvalidPaginationQueryException natively if it cannot build the query!
        // Let's trigger it by passing invalid request DTO arguments that cause DescriptorBuilder to fail.
        // For example, if `after` is greater than `before`?
        // AuthoritativeAuditAdminQueryRequestDTO validates `after` <= `before` in its constructor, we can't pass it.
        // Maybe something in PdoPaginator throws InvalidPaginationQueryException?
        // PdoPaginator doesn't even have `InvalidPaginationQueryException`.

        // Let's just mock PDO prepare to return false? No, that throws PaginationExecutionException.

        // How to trigger InvalidPaginationConfigurationException / InvalidPaginationQueryException ?
        // We can extend AuthoritativeAuditAdminQueryDescriptorBuilder using an anonymous class and inject it!
        // But it is final! We CANNOT extend final classes!
        // Wait, the interface is AuthoritativeAuditAdminQueryInterface.
        // What if we just use Reflection to replace the `paginator` with an object of an anonymous class that implements the same methods?
        // Paginator doesn't have an interface! It's a concrete class `PdoPaginator`.

        // Actually, we can use a dirty trick: we can use a custom anonymous class that duck-types PdoPaginator! PHP doesn't check type if there is no type hint on the property!
        // The property `private PdoPaginator $paginator;` DOES have a type hint!

        // Wait! The repository catches InvalidPaginationConfigurationException!
        // Can we just throw InvalidPaginationConfigurationException from PDOStatement? No, it's not related.

        // Look at earlier output:
        // When we passed `page: 0` before, it just didn't throw! It just went through to PdoPaginator and PDO failed.

        // Wait, we CAN just use a mock for PDO and let PdoPaginator throw InvalidPaginationQueryException?
        // Actually, we can just test that the catch block is there by reflecting and replacing the paginator property with a mock if it wasn't final... but it is.

        // Wait! We can mock the PDOStatement fetch method to throw InvalidPaginationQueryException?
        // No, fetch throws PDOException.

        // What if we throw it from RowMapper map method? RowMapper is called in paginate() closure!
        // If we inject an invalid date, RowMapper throws Exception, mapRow wraps in AuthoritativeAuditStorageException.

        // There MUST be a native way in `maatify/persistence` to trigger `InvalidPaginationConfigurationException`.
        // "Pagination count cannot be negative" throws `PaginationExecutionException`.
        // Let's look at PdoPaginator code you printed earlier:
        // No mentions of InvalidPaginationConfigurationException or InvalidPaginationQueryException in PdoPaginator!!
        // That means they are thrown by something else! Like PaginationConfig or SortWhitelist.
        // But PaginationConfig is instantiated inside `createPaginationConfig()` which is private and hardcoded valid arguments!
        // So they CANNOT be thrown naturally from PdoPaginator or config!

        // They CAN be thrown by DescriptorBuilder!
        // Let's just use the `throw` inside a proxy PDO statement? No, the catch block is around `$this->paginator->paginate(...)`.
        // We can throw InvalidPaginationConfigurationException from PDO::prepare ? YES! PDO is an interface/class we can mock.
        // If we mock PDO::prepare to throw InvalidPaginationConfigurationException, it will be caught by the block!

        $pdo->method("prepare")->willThrowException(new \Maatify\Persistence\Exception\InvalidPaginationConfigurationException("from PDO"));
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);

        $stmtTotal = $this->createMock(PDOStatement::class);
        $stmtFiltered = $this->createMock(PDOStatement::class);
        $stmtData = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturnOnConsecutiveCalls($stmtTotal, $stmtFiltered, $stmtData);

        // PdoPaginator binds parameters! We must mock bindValue to return true.
        $stmtTotal->method("bindValue")->willReturn(true);
        $stmtFiltered->method("bindValue")->willReturn(true);
        $stmtData->method("bindValue")->willReturn(true);

        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("errorCode")->willReturn("00000");
        $stmtTotal->method("fetch")->willReturnOnConsecutiveCalls(["count" => 1], false);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("columnCount")->willReturn(1);
        $stmtFiltered->method("errorCode")->willReturn("00000");
        $stmtFiltered->method("fetch")->willReturnOnConsecutiveCalls(["count" => 1], false);

        $stmtData->method("execute")->willReturn(true);
        $stmtData->method("errorCode")->willReturn("00000");
        $stmtData->method("fetch")->willReturnOnConsecutiveCalls(
            ["event_id" => "mock-event", "occurred_at" => "invalid-date"],
            false
        );

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage("Failed to map AuthoritativeAudit row: Invalid occurred_at format");

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }
}
