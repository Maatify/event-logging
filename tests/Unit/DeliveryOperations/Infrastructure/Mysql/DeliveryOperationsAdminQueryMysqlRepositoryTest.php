<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Infrastructure\Mysql;

use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryExecutionException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsAdminQueryMysqlRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class DeliveryOperationsAdminQueryMysqlRepositoryTest extends TestCase
{
    /** @var PDO&MockObject */
    private PDO $pdo;
    /** @var PDOStatement&MockObject */
    private PDOStatement $statement;
    /** @var PDOStatement&MockObject */
    private PDOStatement $countStatement;
    private DeliveryOperationsAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->statement = $this->createMock(PDOStatement::class);
        $this->countStatement = $this->createMock(PDOStatement::class);
        $this->repository = new DeliveryOperationsAdminQueryMysqlRepository($this->pdo);
    }

    public function testItAdaptsResultCorrectly(): void
    {
        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                if (!str_contains($sql, 'COUNT(*)')) {
                    // verify order by
                    $this->assertStringContainsString('ORDER BY `occurred_at` DESC, `id` DESC', $sql);
                }
                if (str_contains($sql, 'COUNT(*)')) {
                    return $this->countStatement;
                }
                return $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('bindValue')->willReturn(true);

        $this->countStatement->expects($this->exactly(2))
            ->method('columnCount')
            ->willReturn(1);

        $this->countStatement->expects($this->exactly(4))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false);

        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $this->statement->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                [
                    'id' => '1',
                    'event_id' => 'evt-1',
                    'channel' => 'chan-1',
                    'operation_type' => 'op-1',
                    'actor_type' => 'act-1',
                    'actor_id' => '42',
                    'target_type' => 'tar-1',
                    'target_id' => '43',
                    'status' => 'stat-1',
                    'attempt_no' => '0',
                    'scheduled_at' => null,
                    'completed_at' => null,
                    'correlation_id' => null,
                    'request_id' => null,
                    'provider' => null,
                    'provider_message_id' => null,
                    'error_code' => null,
                    'error_message' => null,
                    'metadata' => null,
                    'occurred_at' => '2023-01-01 00:00:00.000000',
                ],
                false
            );

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 20);
        $result = $this->repository->paginate($request);

        $this->assertEquals(1, $result->page);
        $this->assertEquals(20, $result->perPage);
        $this->assertEquals(1, $result->total);
        $this->assertEquals(1, $result->filtered);
        $this->assertEquals(1, $result->totalPages);
        $this->assertFalse($result->hasNext);
        $this->assertFalse($result->hasPrevious);
        $this->assertEquals('occurred_at', $result->sortBy);
        $this->assertEquals('DESC', $result->sortDirection);

        $this->assertCount(1, $result->items);
        $this->assertEquals(1, $result->items[0]->id);
    }

    public function testItTranslatesPDOExceptionToStorageException(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Connection failed'));

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 20);

        try {
            $this->repository->paginate($request);
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to query DeliveryOperations records: Connection failed', $e->getMessage());
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    public function testItTranslatesPaginationExecutionException(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                // Return a statement that fails column count, causing PdoPaginator to throw PaginationExecutionException
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('columnCount')->willReturn(0); // 0 columns forces exception in pagination total
                return $stmt;
            });

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 20);

        try {
            $this->repository->paginate($request);
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to query DeliveryOperations records: Pagination count query must return exactly one column.', $e->getMessage());
            $this->assertInstanceOf(\Maatify\Persistence\Exception\PaginationExecutionException::class, $e->getPrevious());
        }
    }

    public function testItClampsPerPageDefaultToTwenty(): void
    {
        $this->pdo->method('prepare')
            ->willReturnCallback(function (string $sql) {
                return str_contains($sql, 'COUNT(*)') ? $this->countStatement : $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 0], false, ['COUNT(*)' => 0], false);
        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1);
        $result = $this->repository->paginate($request);
        $this->assertSame(20, $result->perPage);
    }

    public function testItClampsPerPageMinToOne(): void
    {
        $this->pdo->method('prepare')
            ->willReturnCallback(function (string $sql) {
                return str_contains($sql, 'COUNT(*)') ? $this->countStatement : $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 0], false, ['COUNT(*)' => 0], false);
        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 0);
        $result = $this->repository->paginate($request);
        $this->assertSame(1, $result->perPage);
    }

    public function testItClampsPerPageMaxToTwoHundred(): void
    {
        $this->pdo->method('prepare')
            ->willReturnCallback(function (string $sql) {
                return str_contains($sql, 'COUNT(*)') ? $this->countStatement : $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 0], false, ['COUNT(*)' => 0], false);
        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 500);
        $result = $this->repository->paginate($request);
        $this->assertSame(200, $result->perPage);
    }

    public function testItDoesNotStartTransactionOnSuccess(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')
            ->willReturnCallback(function (string $sql) {
                return str_contains($sql, 'COUNT(*)') ? $this->countStatement : $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 0], false, ['COUNT(*)' => 0], false);
        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
    }

    public function testItDoesNotStartTransactionOnPDOException(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Connection failed'));

        try {
            $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    public function testItDoesNotStartTransactionOnPaginationExecutionException(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('columnCount')->willReturn(0);
                return $stmt;
            });

        try {
            $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(\Maatify\Persistence\Exception\PaginationExecutionException::class, $e->getPrevious());
        }
    }

    public function testItDoesNotStartTransactionOnMappingFailure(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')
            ->willReturnCallback(function (string $sql) {
                return str_contains($sql, 'COUNT(*)') ? $this->countStatement : $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false);
        $this->countStatement->method('errorCode')->willReturn('00000');

        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('bindValue')->willReturn(true);
        $this->statement->method('errorCode')->willReturn('00000');
        $this->statement->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'id' => '1',
                'occurred_at' => 'invalid-date-string'
            ]);

        try {
            $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to map DeliveryOperations row:', $e->getMessage());
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious());
        }
    }

    public function testItWrapsMapperFailure(): void
    {
        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COUNT(*)')) {
                    return $this->countStatement;
                }
                return $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('bindValue')->willReturn(true);

        $this->countStatement->expects($this->exactly(2))
            ->method('columnCount')
            ->willReturn(1);

        $this->countStatement->expects($this->exactly(4))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false);

        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $this->statement->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'id' => '1',
                'occurred_at' => 'invalid-date-string'
            ]);

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 20);

        try {
            $this->repository->paginate($request);
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to map DeliveryOperations row:', $e->getMessage());
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious());
        }
    }
}
