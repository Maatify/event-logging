<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\BehaviorTrace;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceAdminQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BehaviorTraceAdminQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private BehaviorTraceAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for BehaviorTrace Admin Query integration tests.');
        }

        $this->pdo = new PDO(
            $dsn,
            getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root',
            getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        $this->resetSchema();
        $this->repository = new BehaviorTraceAdminQueryMysqlRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if (isset($this->pdo)) {
            $this->pdo->exec('TRUNCATE TABLE maa_event_logging_behavior_trace');
        }
        parent::tearDown();
    }

    public function testNoFilterTotalsDataDefaultSortAndNativePreparedStatements(): void
    {
        $this->seedDefaultRows();

        $result = $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO(perPage: 20));

        $this->assertFalse((bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        $this->assertSame(5, $result->total);
        $this->assertSame(5, $result->filtered);
        $this->assertCount(5, $result->items);
        $this->assertSame('evt-5', $result->items[0]->eventId);
        $this->assertSame('occurred_at', $result->sortBy);
        $this->assertSame('DESC', $result->sortDirection);
        $this->assertSame(['valid' => true], $result->items[0]->metadata);
    }

    public function testEveryScalarFilterAndMultipleFilters(): void
    {
        $this->seedDefaultRows();

        $this->assertSame(['evt-1', 'evt-2', 'evt-5'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(actorType: 'user', sortDirection: 'ASC')));
        $this->assertSame(['evt-5', 'evt-1'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(actorType: 'user', actorId: 10)));
        $this->assertSame(['evt-3'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(action: 'export')));
        $this->assertSame(['evt-1', 'evt-2', 'evt-5'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(entityType: 'document', sortDirection: 'ASC')));
        $this->assertSame(['evt-2'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(entityType: 'document', entityId: 200)));
        $this->assertSame(['evt-3'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(requestId: 'req-3')));
        $this->assertSame(['evt-4'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(correlationId: '00000000-0000-0000-0000-000000000004')));
        $this->assertSame(['evt-5'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(
            actorType: 'user',
            actorId: 10,
            action: 'view',
            entityType: 'document',
            entityId: 100,
            requestId: 'req-5',
            correlationId: '00000000-0000-0000-0000-000000000005',
        )));
    }

    public function testDateBoundariesAreInclusiveAndEqualBoundariesAreValid(): void
    {
        $this->seedDefaultRows();

        $this->assertSame(['evt-3', 'evt-5', 'evt-4'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC',
        )));
        $this->assertSame(['evt-1', 'evt-2'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(
            before: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC',
        )));
        $this->assertSame(['evt-2'], $this->eventIds(new BehaviorTraceAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC',
        )));
    }

    public function testZeroRowsPaginationPageNormalizationPerPageClampTieBreakerAndCounts(): void
    {
        $this->seedDefaultRows();

        $zero = $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO(actorType: 'missing'));
        $this->assertSame(5, $zero->total);
        $this->assertSame(0, $zero->filtered);
        $this->assertSame(0, $zero->totalPages);
        $this->assertSame(1, $zero->page);
        $this->assertSame([], $zero->items);

        $first = $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO(page: 1, perPage: 2, sortBy: 'occurred_at', sortDirection: 'ASC'));
        $this->assertSame(['evt-1', 'evt-2'], array_map(static fn ($item): string => $item->eventId, $first->items));
        $this->assertTrue($first->hasNext);
        $this->assertFalse($first->hasPrevious);

        $second = $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO(page: 2, perPage: 2, sortBy: 'occurred_at', sortDirection: 'ASC'));
        $this->assertSame(['evt-3', 'evt-5'], array_map(static fn ($item): string => $item->eventId, $second->items));
        $this->assertTrue($second->hasNext);
        $this->assertTrue($second->hasPrevious);

        $overflow = $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO(page: 99, perPage: 2));
        $this->assertSame(1, $overflow->page);
        $this->assertSame(3, $overflow->totalPages);

        $clamped = $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO(perPage: 999, sortBy: 'id', sortDirection: 'bad'));
        $this->assertSame(200, $clamped->perPage);
        $this->assertSame('occurred_at', $clamped->sortBy);
        $this->assertSame('DESC', $clamped->sortDirection);
        $this->assertSame('evt-5', $clamped->items[0]->eventId);
    }

    public function testNullableColumnsAndTransactionOwnershipRules(): void
    {
        $this->insertBehaviorTraceRow('evt-null', 'system', null, 'view', null, null, null, null, null, null, null, [], '2024-01-01 10:00:00.000000');

        $item = $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO())->items[0];

        $this->assertNull($item->context->actorId);
        $this->assertNull($item->entityType);
        $this->assertNull($item->entityId);
        $this->assertNull($item->context->requestId);
        $this->assertSame([], $item->metadata);

        $this->assertFalse($this->pdo->inTransaction());
        $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO());
        $this->assertFalse($this->pdo->inTransaction());

        $this->pdo->beginTransaction();
        $this->repository->paginate(new BehaviorTraceAdminQueryRequestDTO());
        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
    }

    /**
     * @return list<string>
     */
    private function eventIds(BehaviorTraceAdminQueryRequestDTO $request): array
    {
        return array_map(
            static fn ($item): string => $item->eventId,
            $this->repository->paginate($request)->items,
        );
    }

    private function seedDefaultRows(): void
    {
        $this->insertBehaviorTraceRow('evt-1', 'user', 10, 'view', 'document', 100, 'req-1', '00000000-0000-0000-0000-000000000001', 'api.one', '127.0.0.1', 'agent', ['row' => 1], '2024-01-01 10:00:00.000000');
        $this->insertBehaviorTraceRow('evt-2', 'user', 20, 'view', 'document', 200, 'req-2', '00000000-0000-0000-0000-000000000002', null, null, null, ['row' => 2], '2024-01-01 11:00:00.000000');
        $this->insertBehaviorTraceRow('evt-3', 'system', 30, 'export', 'report', 300, 'req-3', '00000000-0000-0000-0000-000000000003', null, null, null, ['row' => 3], '2024-01-01 12:00:00.000000');
        $this->insertBehaviorTraceRow('evt-4', 'admin', 40, 'view', 'invoice', 400, 'req-4', '00000000-0000-0000-0000-000000000004', null, null, null, ['row' => 4], '2024-01-01 13:00:00.000000');
        $this->insertBehaviorTraceRow('evt-5', 'user', 10, 'view', 'document', 100, 'req-5', '00000000-0000-0000-0000-000000000005', null, null, null, ['valid' => true], '2024-01-01 13:00:00.000000');
    }

    private function resetSchema(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../../src/BehaviorTrace/Database/schema.maa_event_logging_behavior_trace.sql');
        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_behavior_trace');
        $this->pdo->exec($schema);
        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_behavior_trace');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function insertBehaviorTraceRow(
        string $eventId,
        string $actorType,
        ?int $actorId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?string $requestId,
        ?string $correlationId,
        ?string $routeName,
        ?string $ipAddress,
        ?string $userAgent,
        array $metadata,
        string $occurredAt,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO maa_event_logging_behavior_trace (
                event_id, actor_type, actor_id, action, entity_type, entity_id,
                metadata, correlation_id, request_id, route_name, ip_address, user_agent, occurred_at
            ) VALUES (
                :event_id, :actor_type, :actor_id, :action, :entity_type, :entity_id,
                :metadata, :correlation_id, :request_id, :route_name, :ip_address, :user_agent, :occurred_at
            )',
        );
        $stmt->execute([
            'event_id' => $eventId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
            'route_name' => $routeName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'occurred_at' => $occurredAt,
        ]);
    }
}
