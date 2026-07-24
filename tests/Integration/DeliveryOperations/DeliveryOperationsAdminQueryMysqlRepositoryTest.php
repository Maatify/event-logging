<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\DeliveryOperations;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsAdminQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeliveryOperationsAdminQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private DeliveryOperationsAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (empty($dsn)) {
            throw new RuntimeException('Missing EVENT_LOGGING_TEST_MYSQL_DSN');
        }
        $user = getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '';

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $schema = file_get_contents(__DIR__ . '/../../../src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql');
        if (!is_string($schema)) {
            throw new RuntimeException('Failed to load schema.');
        }

        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_delivery_operations;');
        $this->pdo->exec($schema);

        $this->repository = new DeliveryOperationsAdminQueryMysqlRepository($this->pdo);
    }

    private function insertLog(
        string $eventId = 'evt-1',
        string $channel = 'chan-1',
        string $operationType = 'op-1',
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        string $status = 'success',
        int $attemptNo = 0,
        ?string $scheduledAt = null,
        ?string $completedAt = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $provider = null,
        ?string $providerMessageId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $metadata = '{}',
        string $occurredAt = '2025-01-01 10:00:00.000000'
    ): void {
        $stmt = $this->pdo->prepare('INSERT INTO maa_event_logging_delivery_operations (
            event_id, channel, operation_type, actor_type, actor_id, target_type, target_id,
            status, attempt_no, scheduled_at, completed_at, correlation_id, request_id,
            provider, provider_message_id, error_code, error_message, metadata, occurred_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )');

        $stmt->execute([
            $eventId, $channel, $operationType, $actorType, $actorId, $targetType, $targetId,
            $status, $attemptNo, $scheduledAt, $completedAt, $correlationId, $requestId,
            $provider, $providerMessageId, $errorCode, $errorMessage, $metadata, $occurredAt
        ]);
    }

    public function testItFiltersByEqualityFilters(): void
    {
        $this->insertLog(eventId: 'eq-1');
        $this->insertLog(eventId: 'eq-2');

        $result = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'eq-1'));
        $this->assertSame(1, $result->filtered);
        $this->assertSame('eq-1', $result->items[0]->eventId);

        $this->insertLog(eventId: 'eq-3', channel: 'chan-2');
        $result = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(channel: 'chan-2'));
        $this->assertSame(1, $result->filtered);
        $this->assertSame('chan-2', $result->items[0]->channel);

        $this->insertLog(eventId: 'eq-4', operationType: 'op-2');
        $result = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(operationType: 'op-2'));
        $this->assertSame(1, $result->filtered);
        $this->assertSame('op-2', $result->items[0]->operationType);

        $this->insertLog(eventId: 'eq-5', status: 'failed');
        $result = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(status: 'failed'));
        $this->assertSame(1, $result->filtered);
        $this->assertSame('failed', $result->items[0]->status);
    }

    public function testItFiltersActorIndependently(): void
    {
        $this->insertLog(eventId: 'e1', actorType: 'SYS', actorId: 10);
        $this->insertLog(eventId: 'e2', actorType: 'SYS', actorId: 20);
        $this->insertLog(eventId: 'e3', actorType: 'USR', actorId: 10);
        $this->insertLog(eventId: 'e4', actorType: null, actorId: null);

        // type only
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(actorType: 'SYS'));
        $this->assertSame(2, $res->filtered);

        // id only
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(actorId: 10));
        $this->assertSame(2, $res->filtered);

        // both
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(actorType: 'SYS', actorId: 10));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e1', $res->items[0]->eventId);

        // neither
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
        $this->assertSame(4, $res->filtered);
    }

    public function testItFiltersTargetIndependently(): void
    {
        $this->insertLog(eventId: 'e1', targetType: 'DOC', targetId: 10);
        $this->insertLog(eventId: 'e2', targetType: 'DOC', targetId: 20);
        $this->insertLog(eventId: 'e3', targetType: 'IMG', targetId: 10);
        $this->insertLog(eventId: 'e4', targetType: null, targetId: null);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(targetType: 'DOC'));
        $this->assertSame(2, $res->filtered);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(targetId: 10));
        $this->assertSame(2, $res->filtered);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(targetType: 'DOC', targetId: 10));
        $this->assertSame(1, $res->filtered);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
        $this->assertSame(4, $res->filtered);
    }

    public function testItFiltersAttemptRanges(): void
    {
        $this->insertLog(eventId: 'att-1', attemptNo: 0);
        $this->insertLog(eventId: 'att-2', attemptNo: 2);
        $this->insertLog(eventId: 'att-3', attemptNo: 5);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(attemptNoMin: 2));
        $this->assertSame(2, $res->filtered);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(attemptNoMax: 2));
        $this->assertSame(2, $res->filtered);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(attemptNoMin: 2, attemptNoMax: 4));
        $this->assertSame(1, $res->filtered);
    }

    public function testItFiltersDateRangesIndependentlyAndCombinedWithInclusiveBoundaries(): void
    {
        $this->insertLog(eventId: 'e1', scheduledAt: '2023-01-01 10:00:00', completedAt: '2023-01-02 10:00:00', occurredAt: '2023-01-03 10:00:00');
        $this->insertLog(eventId: 'e2', scheduledAt: '2023-01-01 11:00:00', completedAt: '2023-01-02 11:00:00', occurredAt: '2023-01-03 11:00:00');
        $this->insertLog(eventId: 'e3', scheduledAt: '2023-01-01 12:00:00', completedAt: '2023-01-02 12:00:00', occurredAt: '2023-01-03 12:00:00');

        $utc = new DateTimeZone('UTC');

        // scheduled
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            scheduledAfter: new DateTimeImmutable('2023-01-01 11:00:00', $utc),
            scheduledBefore: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e2', $res->items[0]->eventId);

        // completed
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            completedAfter: new DateTimeImmutable('2023-01-02 11:00:00', $utc),
            completedBefore: new DateTimeImmutable('2023-01-02 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e2', $res->items[0]->eventId);

        // occurred
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2023-01-03 11:00:00', $utc),
            before: new DateTimeImmutable('2023-01-03 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e2', $res->items[0]->eventId);

        // combined
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            scheduledAfter: new DateTimeImmutable('2023-01-01 10:00:00', $utc),
            completedBefore: new DateTimeImmutable('2023-01-02 11:00:00', $utc),
            after: new DateTimeImmutable('2023-01-03 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e2', $res->items[0]->eventId);
    }

    public function testItFiltersNullStateProperly(): void
    {
        $this->insertLog(eventId: 'e1', errorCode: 'err-1', provider: null);
        $this->insertLog(eventId: 'e2', errorCode: null, provider: 'prov-1');

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(nullStateFilters: [
            'errorCode' => true,
        ]));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e2', $res->items[0]->eventId);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(nullStateFilters: [
            'errorCode' => false,
            'provider' => true,
        ]));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e1', $res->items[0]->eventId);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            errorCode: 'err-1',
            nullStateFilters: ['errorCode' => true] // Conflicting state, safely returns 0
        ));
        $this->assertSame(0, $res->filtered);
    }

    public function testItFiltersErrorMessageContainsWithEscaping(): void
    {
        $this->insertLog(eventId: 'e1', errorMessage: 'a\b%c_d');
        $this->insertLog(eventId: 'e2', errorMessage: 'xyz');

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(errorMessageLike: '\b%c_'));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e1', $res->items[0]->eventId);
    }

    public function testItFiltersMetadataWithNullVersusMissingPath(): void
    {
        $this->insertLog(eventId: 'e1', metadata: '{"a": 1, "b": null}');
        $this->insertLog(eventId: 'e2', metadata: '{"a": 1}');

        // Exists and is null
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.b' => null
        ]));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e1', $res->items[0]->eventId);

        // Missing path -> not matched by null search
        $res2 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.c' => null
        ]));
        $this->assertSame(0, $res2->filtered);
    }

    public function testItFiltersMultipleMetadataWithDistinctPlaceholders(): void
    {
        $this->insertLog(eventId: 'e1', metadata: '{"a": "hello", "b": 123}');
        $this->insertLog(eventId: 'e2', metadata: '{"a": "hello", "b": 456}');

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.a' => 'hello',
            '$.b' => 123
        ]));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('e1', $res->items[0]->eventId);
    }

    public function testItPaginatesWithCountDataParityAndDeterministicTieBreaking(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog(eventId: "evt-{$i}", occurredAt: '2023-01-01 10:00:00.000000');
        }

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 2));
        $this->assertSame(5, $res->total);
        $this->assertSame(5, $res->filtered);
        $this->assertSame(3, $res->totalPages);
        $this->assertCount(2, $res->items);
        $this->assertSame('evt-5', $res->items[0]->eventId); // Sorted ID desc because occurredAt is same
        $this->assertSame('evt-4', $res->items[1]->eventId);

        $res2 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 3, perPage: 2));
        $this->assertCount(1, $res2->items);
        $this->assertSame('evt-1', $res2->items[0]->eventId);
    }

    public function testItPreservesCallerOwnedTransaction(): void
    {
        $this->pdo->beginTransaction();

        $this->insertLog(eventId: 'tx-evt');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'tx-evt'));
        $this->assertSame(1, $res->filtered);

        $this->pdo->rollBack();

        $res2 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'tx-evt'));
        $this->assertSame(0, $res2->filtered);
    }

    public function testItTranslatesRealPdoExceptionToStorageException(): void
    {
        $this->pdo->exec('DROP TABLE maa_event_logging_delivery_operations');

        $this->expectException(DeliveryOperationsStorageException::class);
        $this->expectExceptionMessage('Failed to query DeliveryOperations records:');

        try {
            $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
            throw $e;
        }
    }
}
