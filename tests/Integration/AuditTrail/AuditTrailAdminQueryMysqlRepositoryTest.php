<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuditTrail;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailAdminQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditTrailAdminQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AuditTrailAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for AuditTrail Admin Query integration tests.');
        }

        $this->pdo = new PDO(
            $dsn,
            getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root',
            getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->resetSchema();
        $this->repository = new AuditTrailAdminQueryMysqlRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if (isset($this->pdo)) {
            $this->pdo->exec('TRUNCATE TABLE maa_event_logging_audit_trail');
        }
        parent::tearDown();
    }

    public function testNoFilterTotalsDataDefaultSortAndNativePreparedStatements(): void
    {
        $this->seedDefaultRows();

        $result = $this->repository->paginate(new AuditTrailAdminQueryRequestDTO(perPage: 20));

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

        $this->assertSame(['evt-1', 'evt-2', 'evt-5'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(actorType: 'user', sortDirection: 'ASC')));
        $this->assertSame(['evt-5', 'evt-1'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(actorType: 'user', actorId: 10)));
        $this->assertSame(['evt-3'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(eventKey: 'export')));
        $this->assertSame(['evt-1', 'evt-2', 'evt-5'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(entityType: 'document', sortDirection: 'ASC')));
        $this->assertSame(['evt-2'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(entityType: 'document', entityId: 200)));
        $this->assertSame(['evt-1', 'evt-3', 'evt-5'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(subjectType: 'account', sortDirection: 'ASC')));
        $this->assertSame(['evt-5'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(subjectType: 'account', subjectId: 500)));
        $this->assertSame(['evt-3'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(requestId: 'req-3')));
        $this->assertSame(['evt-4'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(correlationId: '00000000-0000-0000-0000-000000000004')));
        $this->assertSame(['evt-5'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(
            actorType: 'user',
            actorId: 10,
            eventKey: 'view',
            entityType: 'document',
            entityId: 100,
            subjectType: 'account',
            subjectId: 500,
            requestId: 'req-5',
            correlationId: '00000000-0000-0000-0000-000000000005'
        )));
    }

    public function testDateBoundariesAreInclusiveAndEqualBoundariesAreValid(): void
    {
        $this->seedDefaultRows();

        $this->assertSame(['evt-3', 'evt-5', 'evt-4'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC'
        )));
        $this->assertSame(['evt-1', 'evt-2'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(
            before: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC'
        )));
        $this->assertSame(['evt-2'], $this->eventIds(new AuditTrailAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC'
        )));
    }

    public function testZeroRowsPaginationAndOverflowReset(): void
    {
        $this->seedDefaultRows();

        $zero = $this->repository->paginate(new AuditTrailAdminQueryRequestDTO(actorType: 'missing'));
        $this->assertSame(5, $zero->total);
        $this->assertSame(0, $zero->filtered);
        $this->assertSame(0, $zero->totalPages);
        $this->assertSame(1, $zero->page);
        $this->assertSame([], $zero->items);

        $overflow = $this->repository->paginate(new AuditTrailAdminQueryRequestDTO(page: 99, perPage: 2));
        $this->assertSame(1, $overflow->page);
        $this->assertSame(3, $overflow->totalPages);
    }

    public function testFirstAndLaterPagesPerPageClampRequestedSortAndTieBreaker(): void
    {
        $this->seedDefaultRows();

        $first = $this->repository->paginate(new AuditTrailAdminQueryRequestDTO(page: 1, perPage: 2, sortBy: 'occurred_at', sortDirection: 'ASC'));
        $this->assertSame(['evt-1', 'evt-2'], array_map(static fn ($item): string => $item->eventId, $first->items));
        $this->assertTrue($first->hasNext);
        $this->assertFalse($first->hasPrevious);
        $this->assertSame('ASC', $first->sortDirection);

        $second = $this->repository->paginate(new AuditTrailAdminQueryRequestDTO(page: 2, perPage: 2, sortBy: 'occurred_at', sortDirection: 'ASC'));
        $this->assertSame(['evt-3', 'evt-5'], array_map(static fn ($item): string => $item->eventId, $second->items));
        $this->assertTrue($second->hasNext);
        $this->assertTrue($second->hasPrevious);

        $clamped = $this->repository->paginate(new AuditTrailAdminQueryRequestDTO(perPage: 999, sortBy: 'id', sortDirection: 'bad'));
        $this->assertSame(200, $clamped->perPage);
        $this->assertSame('occurred_at', $clamped->sortBy);
        $this->assertSame('DESC', $clamped->sortDirection);
        $this->assertSame('evt-5', $clamped->items[0]->eventId);
    }

    public function testNullableColumnsAndExplicitSelectedColumnMapping(): void
    {
        $this->insertAuditTrailRow('evt-null', 'system', null, 'view', 'document', null, null, null, null, null, null, null, null, null, null, null, [], '2024-01-01 10:00:00.000000');

        $item = $this->repository->paginate(new AuditTrailAdminQueryRequestDTO())->items[0];

        $this->assertNull($item->actorId);
        $this->assertNull($item->entityId);
        $this->assertNull($item->subjectType);
        $this->assertNull($item->subjectId);
        $this->assertNull($item->referrerRouteName);
        $this->assertSame([], $item->metadata);
    }

    public function testTransactionOwnershipRules(): void
    {
        $this->seedDefaultRows();

        $this->assertFalse($this->pdo->inTransaction());
        $this->repository->paginate(new AuditTrailAdminQueryRequestDTO());
        $this->assertFalse($this->pdo->inTransaction());

        $this->pdo->beginTransaction();
        $this->repository->paginate(new AuditTrailAdminQueryRequestDTO());
        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
    }

    /**
     * @return list<string>
     */
    private function eventIds(AuditTrailAdminQueryRequestDTO $request): array
    {
        return array_map(
            static fn ($item): string => $item->eventId,
            $this->repository->paginate($request)->items
        );
    }

    private function seedDefaultRows(): void
    {
        $this->insertAuditTrailRow('evt-1', 'user', 10, 'view', 'document', 100, 'account', 400, 'req-1', '00000000-0000-0000-0000-000000000001', 'route_1', '/one', 'example.test', 'api.one', '127.0.0.1', 'agent', ['row' => 1], '2024-01-01 10:00:00.000000');
        $this->insertAuditTrailRow('evt-2', 'user', 20, 'view', 'document', 200, null, null, 'req-2', '00000000-0000-0000-0000-000000000002', null, null, null, null, null, null, ['row' => 2], '2024-01-01 11:00:00.000000');
        $this->insertAuditTrailRow('evt-3', 'system', 30, 'export', 'report', 300, 'account', 400, 'req-3', '00000000-0000-0000-0000-000000000003', null, null, null, null, null, null, ['row' => 3], '2024-01-01 12:00:00.000000');
        $this->insertAuditTrailRow('evt-4', 'admin', 40, 'view', 'invoice', 400, null, null, 'req-4', '00000000-0000-0000-0000-000000000004', null, null, null, null, null, null, ['row' => 4], '2024-01-01 13:00:00.000000');
        $this->insertAuditTrailRow('evt-5', 'user', 10, 'view', 'document', 100, 'account', 500, 'req-5', '00000000-0000-0000-0000-000000000005', null, null, null, null, null, null, ['valid' => true], '2024-01-01 13:00:00.000000');
    }

    private function resetSchema(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../../src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql');
        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_audit_trail');
        $this->pdo->exec($schema);
        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_audit_trail');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function insertAuditTrailRow(
        string $eventId,
        string $actorType,
        ?int $actorId,
        string $eventKey,
        string $entityType,
        ?int $entityId,
        ?string $subjectType,
        ?int $subjectId,
        ?string $requestId,
        ?string $correlationId,
        ?string $referrerRouteName,
        ?string $referrerPath,
        ?string $referrerHost,
        ?string $routeName,
        ?string $ipAddress,
        ?string $userAgent,
        array $metadata,
        string $occurredAt
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO maa_event_logging_audit_trail (
                event_id, actor_type, actor_id, event_key, entity_type, entity_id,
                subject_type, subject_id, referrer_route_name, referrer_path, referrer_host,
                correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at
            ) VALUES (
                :event_id, :actor_type, :actor_id, :event_key, :entity_type, :entity_id,
                :subject_type, :subject_id, :referrer_route_name, :referrer_path, :referrer_host,
                :correlation_id, :request_id, :route_name, :ip_address, :user_agent, :metadata, :occurred_at
            )'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'event_key' => $eventKey,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'referrer_route_name' => $referrerRouteName,
            'referrer_path' => $referrerPath,
            'referrer_host' => $referrerHost,
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
            'route_name' => $routeName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt,
        ]);
    }
}
