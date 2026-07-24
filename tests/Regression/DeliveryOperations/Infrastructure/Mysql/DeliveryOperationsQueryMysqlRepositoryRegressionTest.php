<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\DeliveryOperations\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsQueryDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsQueryMysqlRepository;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class DeliveryOperationsQueryMysqlRepositoryRegressionTest extends TestCase
{
    /** @var PDO&MockObject */
    private PDO $pdo;
    /** @var PDOStatement&MockObject */
    private PDOStatement $statement;
    private DeliveryOperationsQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->statement = $this->createMock(PDOStatement::class);
        $this->repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
    }

    public function testPrimitiveRepositoryBuildsCorrectSqlAndPreservesLimitWithoutClamping(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                return str_contains($sql, 'SELECT * FROM maa_event_logging_delivery_operations')
                    && str_contains($sql, 'WHERE actor_type = :actor_type AND actor_id = :actor_id AND target_type = :target_type AND target_id = :target_id AND channel = :channel AND operation_type = :operation_type AND status = :status AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before AND (occurred_at < :cursor_at OR (occurred_at = :cursor_at AND id < :cursor_id))')
                    && str_contains($sql, 'ORDER BY occurred_at DESC, id DESC LIMIT 500');
            }))
            ->willReturn($this->statement);

        $this->statement->expects($this->once())
            ->method('execute')
            ->with([
                'actor_type' => 'act-1',
                'actor_id' => 42,
                'target_type' => 'tar-1',
                'target_id' => 43,
                'channel' => 'chan-1',
                'operation_type' => 'op-1',
                'status' => 'stat-1',
                'request_id' => 'req-1',
                'correlation_id' => 'cor-1',
                'after' => '2023-01-01 00:00:00.000000',
                'before' => '2023-01-02 00:00:00.000000',
                'cursor_at' => '2023-01-01 12:00:00.000000',
                'cursor_id' => 99,
            ]);

        $this->statement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $query = new DeliveryOperationsQueryDTO(
            actorType: 'act-1',
            actorId: 42,
            targetType: 'tar-1',
            targetId: 43,
            channel: 'chan-1',
            operationType: 'op-1',
            status: 'stat-1',
            correlationId: 'cor-1',
            requestId: 'req-1',
            after: new DateTimeImmutable('2023-01-01', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2023-01-02', new DateTimeZone('UTC')),
            cursorOccurredAt: new DateTimeImmutable('2023-01-01 12:00:00', new DateTimeZone('UTC')),
            cursorId: 99,
            limit: 500 // Exceeds Admin max 200, proves primitive limit clamp (max(1, limit))
        );

        $this->repository->find($query);
    }

    public function testPrimitiveRepositoryIgnoresCursorIfEitherPartIsMissing(): void
    {
        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                $this->assertStringNotContainsString(':cursor_at', $sql);
                $this->assertStringNotContainsString(':cursor_id', $sql);
                return $this->statement;
            });

        $this->statement->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $params) {
                $this->assertArrayNotHasKey('cursor_at', $params);
                $this->assertArrayNotHasKey('cursor_id', $params);
                return true;
            });

        $this->statement->method('fetchAll')->willReturn([]);

        $this->repository->find(new DeliveryOperationsQueryDTO(
            cursorOccurredAt: new DateTimeImmutable('2023-01-01 12:00:00', new DateTimeZone('UTC')),
            cursorId: null
        ));

        $this->repository->find(new DeliveryOperationsQueryDTO(
            cursorOccurredAt: null,
            cursorId: 99
        ));
    }

    public function testPrimitiveRepositoryAppliesFallbacksCorrectly(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '123',
                'event_id' => 'evt-1',
                // channel missing
                // operationType missing
                // actor_type missing
                // actor_id missing
                // target_type missing
                // target_id missing
                // status missing
                // attempt_no missing
                'scheduled_at' => null,
                'completed_at' => null,
                'correlation_id' => null,
                'request_id' => null,
                'provider' => null,
                'provider_message_id' => null,
                'error_code' => null,
                'error_message' => null,
                'metadata' => '{"foo": "bar"}',
                'occurred_at' => null, // Should fallback to 1970
            ]
        ]);

        $results = $this->repository->find(new DeliveryOperationsQueryDTO());

        $this->assertCount(1, $results);
        $dto = $results[0];
        $this->assertEquals(123, $dto->id);
        $this->assertEquals('evt-1', $dto->eventId);
        $this->assertEquals('', $dto->channel);
        $this->assertEquals('', $dto->operationType);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertEquals('', $dto->status);
        $this->assertEquals(0, $dto->attemptNo);
        $this->assertNull($dto->scheduledAt);
        $this->assertNull($dto->completedAt);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->provider);
        $this->assertNull($dto->providerMessageId);
        $this->assertNull($dto->errorCode);
        $this->assertNull($dto->errorMessage);
        $this->assertEquals(['foo' => 'bar'], $dto->metadata);
        $this->assertEquals('1970-01-01T00:00:00+00:00', $dto->occurredAt->format(\DATE_ATOM));
    }

    public function testPrimitiveRepositoryPDOExceptionMapping(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('PDO failure'));

        $this->expectException(DeliveryOperationsStorageException::class);
        $this->expectExceptionMessage('Failed to query DeliveryOperations records: PDO failure');

        try {
            $this->repository->find(new DeliveryOperationsQueryDTO());
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
            throw $e;
        }
    }

    public function testPrimitiveRepositoryMapperExceptionMapping(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'occurred_at' => 'invalid-date'
            ]
        ]);

        $this->expectException(DeliveryOperationsStorageException::class);
        $this->expectExceptionMessage('Failed to map DeliveryOperations row:');

        try {
            $this->repository->find(new DeliveryOperationsQueryDTO());
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
            throw $e;
        }
    }
}
