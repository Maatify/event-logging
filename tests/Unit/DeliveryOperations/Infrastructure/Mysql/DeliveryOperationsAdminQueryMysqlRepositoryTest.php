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

    public function testItAdaptsResultCorrectlyWithLimitsMaxAndMinPaginationGates(): void
    {
        $this->pdo->expects($this->exactly(9)) // 3 requests * 3 prepares (1 data + 2 count)
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

        $this->countStatement->expects($this->exactly(6))
            ->method('columnCount')
            ->willReturn(1);

        $this->countStatement->expects($this->exactly(12))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                ['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false,
                ['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false,
                ['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false
            );

        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $this->statement->expects($this->exactly(6))
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
                false,
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
                false,
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

        // First request to verify min clamp to page=1, perPage=1
        $requestMin = new DeliveryOperationsAdminQueryRequestDTO(page: 0, perPage: 0);
        $resultMin = $this->repository->paginate($requestMin);
        $this->assertEquals(1, $resultMin->page);
        $this->assertEquals(1, $resultMin->perPage);

        // Second request to verify max clamp to perPage=200
        $requestMax = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 500);
        $resultMax = $this->repository->paginate($requestMax);
        $this->assertEquals(1, $resultMax->page);
        $this->assertEquals(200, $resultMax->perPage);

        // Third request to verify defaults
        $requestDefault = new DeliveryOperationsAdminQueryRequestDTO();
        $resultDefault = $this->repository->paginate($requestDefault);
        $this->assertEquals(1, $resultDefault->page);
        $this->assertEquals(20, $resultDefault->perPage);
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

        $this->expectException(DeliveryOperationsStorageException::class);
        $this->expectExceptionMessage('Failed to map DeliveryOperations row:');

        $this->repository->paginate($request);
    }

    public function testItNeverCallsTransactionMethods(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

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
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');
        $this->countStatement->method('fetch')->willReturnOnConsecutiveCalls(['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false);
        $this->statement->method('fetch')->willReturn(false); // return 0 items

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 20);
        $result = $this->repository->paginate($request);
        $this->assertCount(0, $result->items);
    }

    public function testItNeverCallsTransactionMethodsOnFailure(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Failure'));

        $request = new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 20);
        try {
            $this->repository->paginate($request);
            $this->fail('Expected Exception');
        } catch (DeliveryOperationsStorageException) {
            // expected
        }
    }
}
