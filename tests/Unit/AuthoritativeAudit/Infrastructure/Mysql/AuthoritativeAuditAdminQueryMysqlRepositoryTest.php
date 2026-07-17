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
    public function testInvalidPaginationConfigurationExceptionFactory(): void
    {
        $prev = new InvalidPaginationConfigurationException('Config error');
        $e = AuthoritativeAuditAdminQueryExecutionException::executionFailed($prev);
        $this->assertSame('AuthoritativeAudit Admin Query execution failed: Config error', $e->getMessage());
        $this->assertSame($prev, $e->getPrevious());
        $this->assertSame(0, $e->getCode());
    }

    public function testInvalidPaginationQueryExceptionFactory(): void
    {
        $prev = new InvalidPaginationQueryException('Query error');
        $e = AuthoritativeAuditAdminQueryExecutionException::executionFailed($prev);
        $this->assertSame('AuthoritativeAudit Admin Query execution failed: Query error', $e->getMessage());
        $this->assertSame($prev, $e->getPrevious());
        $this->assertSame(0, $e->getCode());
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
        $stmtCount = $this->createMock(PDOStatement::class);
        $stmtData = $this->createMock(PDOStatement::class);

        $pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtCount, $stmtCount, $stmtData);

        $stmtCount->expects($this->any())->method('execute')->willReturn(true);
        $stmtCount->expects($this->any())->method('columnCount')->willReturn(1);
        $stmtCount->expects($this->any())->method('errorCode')->willReturn('00000');
        // Need bindValue to return true so 'Failed to bind pagination parameter.' is not thrown.
        $stmtCount->expects($this->any())->method('bindValue')->willReturn(true);

        $c1 = 0;
        $stmtCount->expects($this->any())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnCallback(function() use (&$c1) {
                $c1++;
                if ($c1 === 1 || $c1 === 3) return ['c' => '1'];
                return false;
            });

        $stmtData->expects($this->any())->method('execute')->willReturn(true);
        $stmtData->expects($this->any())->method('errorCode')->willReturn('00000');
        $stmtData->expects($this->any())->method('bindValue')->willReturn(true);

        $c2 = 0;
        $stmtData->expects($this->any())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnCallback(function() use (&$c2) {
                $c2++;
                if ($c2 === 1) return ['id_str' => '1', 'occurred_at' => 'totally invalid'];
                return false;
            });

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
        $request = new AuthoritativeAuditAdminQueryRequestDTO();

        try {
            $repository->paginate($request);
            $this->fail('Expected exception was not thrown');
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertStringContainsString('Failed to map AuthoritativeAudit row', $e->getMessage());
            $this->assertInstanceOf(\Exception::class, $e->getPrevious());
        }
    }
}
