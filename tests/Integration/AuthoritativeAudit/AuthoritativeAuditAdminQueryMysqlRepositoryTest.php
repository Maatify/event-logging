<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\AuthoritativeAudit\Support\StrictAuthoritativeAuditMysqlIntegrationTestCase;
use PDO;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository
 */
final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends StrictAuthoritativeAuditMysqlIntegrationTestCase
{
    protected function isStrictMysqlRequired(): bool
    {
        return true;
    }
    private AuthoritativeAuditAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo === null) {
            $this->fail('Integration tests require a real MySQL database.');
        }

        // Enforce native prepared statements strictly for this test
        /** @var PDO $pdo */
        $pdo = $this->pdo;
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->assertSame(0, $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));

        $this->repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql';
    }

    /**
     * @return array<int, string>
     */
    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_authoritative_audit_log',
            'maa_event_logging_authoritative_audit_outbox',
        ];
    }

    public function testNoFilterTotalsDataDefaultSortAndNativePreparedStatements(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt', 1, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'sys', 2, 'act-2', 'tgt', 2, null, 'corr-2', '2024-01-01 11:00:00.000000');

        $request = new AuthoritativeAuditAdminQueryRequestDTO();
        $result = $this->repository->paginate($request);

        $this->assertSame(2, $result->total);
        $this->assertSame(2, $result->filtered);
        $this->assertCount(2, $result->items);
        $this->assertSame('evt-2', $result->items[0]->eventId);
        $this->assertSame('evt-1', $result->items[1]->eventId);
        $this->assertSame('occurred_at', $result->sortBy);
        $this->assertSame('DESC', $result->sortDirection);
    }

    public function testEveryScalarFilterAndMultipleFilters(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt1', 1, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'user', 2, 'act-2', 'tgt2', 2, null, 'corr-2', '2024-01-01 11:00:00.000000');
        $this->insertLog('evt-3', 'sys', 1, 'act-3', 'tgt3', 3, null, 'corr-3', '2024-01-01 12:00:00.000000');

        // isolated eventId
        $req1 = new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-2');
        $res1 = $this->repository->paginate($req1);
        $this->assertSame(1, $res1->filtered);
        $this->assertSame('evt-2', $res1->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'user');
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(actorId: 2);
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'user', actorId: 2);
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'tgt2');
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(targetId: 2);
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'tgt2', targetId: 2);
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(action: 'act-2');
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        $req = new AuthoritativeAuditAdminQueryRequestDTO(correlationId: 'corr-2');
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);

        // combined all
        $req2 = new AuthoritativeAuditAdminQueryRequestDTO(
            actorType: 'sys',
            actorId: 1,
            action: 'act-1',
            targetType: 'tgt1',
            targetId: 1,
            correlationId: 'corr-1'
        );
        $res2 = $this->repository->paginate($req2);
        $this->assertSame(1, $res2->filtered);
        $this->assertSame('evt-1', $res2->items[0]->eventId);
    }

    public function testDateBoundariesAreInclusiveAndConvertAfricaCairoToUtc(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act', 'tgt', 1, null, 'c1', '2024-06-01 08:00:00.000000'); // UTC 08:00 is Cairo 11:00 (GMT+3 in Summer)
        $this->insertLog('evt-2', 'sys', 1, 'act', 'tgt', 1, null, 'c2', '2024-06-01 09:00:00.500000'); // UTC 09:00 is Cairo 12:00
        $this->insertLog('evt-3', 'sys', 1, 'act', 'tgt', 1, null, 'c3', '2024-06-01 10:00:00.000000'); // UTC 10:00 is Cairo 13:00

        $tzCairo = new DateTimeZone('Africa/Cairo');
        $afterCairo = new DateTimeImmutable('2024-06-01 11:00:00.000000', $tzCairo);
        $beforeCairo = new DateTimeImmutable('2024-06-01 12:00:00.500000', $tzCairo);

        $req1 = new AuthoritativeAuditAdminQueryRequestDTO(
            after: $afterCairo,
            before: $beforeCairo
        );

        $res1 = $this->repository->paginate($req1);
        $this->assertSame(2, $res1->filtered);
        $this->assertSame('evt-2', $res1->items[0]->eventId);
        $this->assertSame('evt-1', $res1->items[1]->eventId);

        $req2 = new AuthoritativeAuditAdminQueryRequestDTO(
            after: $afterCairo,
            before: $afterCairo
        );
        $res2 = $this->repository->paginate($req2);
        $this->assertSame(1, $res2->filtered);
        $this->assertSame('evt-1', $res2->items[0]->eventId);
    }

    public function testZeroRowsPaginationAndOverflowReset(): void
    {
        $req = new AuthoritativeAuditAdminQueryRequestDTO(page: 10, perPage: 20);
        $res = $this->repository->paginate($req);

        $this->assertSame(0, $res->total);
        $this->assertSame(0, $res->filtered);
        $this->assertCount(0, $res->items);
        $this->assertSame(1, $res->page);
        $this->assertSame(0, $res->totalPages);
    }

    public function testOverflowPaginationReturnsFirstPageDataAndCanonicalValues(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog("evt-$i", 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 10:00:00.000000');
        }

        $req = new AuthoritativeAuditAdminQueryRequestDTO(page: 10, perPage: 2, sortBy: 'occurred_at', sortDirection: 'asc');
        $res = $this->repository->paginate($req);

        $this->assertSame(5, $res->total);
        $this->assertSame(5, $res->filtered);
        $this->assertSame(3, $res->totalPages);
        $this->assertSame(1, $res->page); // Reset to page 1 because 10 > 3
        $this->assertCount(2, $res->items);

        $this->assertTrue($res->hasNext);
        $this->assertFalse($res->hasPrevious);
        $this->assertSame('evt-5', $res->items[0]->eventId);
        $this->assertSame('evt-4', $res->items[1]->eventId);
    }

    public function testPerPageClampingRules(): void
    {
        $this->insertLog("evt-1", 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 10:00:00.000000');

        $req1 = new AuthoritativeAuditAdminQueryRequestDTO(perPage: 0);
        $res1 = $this->repository->paginate($req1);
        $this->assertSame(1, $res1->perPage);

        $req2 = new AuthoritativeAuditAdminQueryRequestDTO(perPage: 500);
        $res2 = $this->repository->paginate($req2);
        $this->assertSame(200, $res2->perPage);
    }

    public function testStrictAscAndDescOrderingWithDistinctTimestamps(): void
    {
        $this->insertLog("evt-1", 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 10:00:00.000000');
        $this->insertLog("evt-2", 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 11:00:00.000000');

        $reqAsc = new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'occurred_at', sortDirection: 'asc');
        $resAsc = $this->repository->paginate($reqAsc);
        $this->assertSame('evt-1', $resAsc->items[0]->eventId);
        $this->assertSame('evt-2', $resAsc->items[1]->eventId);

        $reqDesc = new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'occurred_at', sortDirection: 'desc');
        $resDesc = $this->repository->paginate($reqDesc);
        $this->assertSame('evt-2', $resDesc->items[0]->eventId);
        $this->assertSame('evt-1', $resDesc->items[1]->eventId);
    }

    public function testIdDescTieBreakerIsAppliedWhenTimestampsAreEqual(): void
    {
        $time = '2024-01-01 10:00:00.000000';
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog("evt-$i", 'sys', 1, 'act', 'tgt', 1, null, 'c', $time);
        }

        $req = new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'occurred_at', sortDirection: 'asc');
        $res = $this->repository->paginate($req);

        $this->assertSame('evt-5', $res->items[0]->eventId);
        $this->assertSame('evt-4', $res->items[1]->eventId);
    }

    public function testNullableColumnsAndExplicitSelectedColumnMapping(): void
    {
        $this->insertLog('evt-min', 'sys', null, 'act', 'tgt', null, null, null, '2024-01-01 10:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-min');
        $res = $this->repository->paginate($req);

        $this->assertCount(1, $res->items);
        $item = $res->items[0];
        $this->assertSame('sys', $item->actorType);
        $this->assertNull($item->actorId);
        $this->assertSame('tgt', $item->targetType);
        $this->assertNull($item->targetId);
        $this->assertNull($item->correlationId);
        $this->assertNull($item->changes);
        $this->assertNull($item->ipAddress);
        $this->assertNull($item->userAgent);
    }

    public function testAssociativeJsonArrayHydrationMapping(): void
    {
        $this->insertLog('evt-json', 'sys', null, 'act', 'tgt', null, '{"old":"data","new":"data2"}', null, '2024-01-01 10:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-json');
        $res = $this->repository->paginate($req);

        $this->assertCount(1, $res->items);
        $this->assertEquals(["old" => "data", "new" => "data2"], $res->items[0]->changes);
    }

    public function testTransactionOwnershipRules(): void
    {
        $pdo = $this->pdo;
        if ($pdo === null) {
            $this->fail('PDO not initialized.');
        }
        $this->assertFalse($pdo->inTransaction());

        $pdo->beginTransaction();
        $this->insertLog('evt-txn', 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 10:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-txn');
        $res = $this->repository->paginate($req);
        $this->assertCount(1, $res->items);

        $this->assertTrue($pdo->inTransaction());

        $pdo->rollBack();

        $res2 = $this->repository->paginate($req);
        $this->assertCount(0, $res2->items);

        // Repository should not catch or alter the transaction state
        $this->assertFalse($pdo->inTransaction());
    }

    public function testReadingFromLogTableOnly(): void
    {
        $pdo = $this->pdo;
        if ($pdo === null) {
            $this->fail('PDO not initialized.');
        }
        // Insert into outbox
        $stmt = $pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_outbox
            (event_id, actor_type, actor_id, action, target_type, target_id, risk_level, payload, correlation_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['evt-outbox', 'sys', 1, 'act', 'tgt', 1, 'LOW', '{}', 'c', '2024-01-01 10:00:00.000000']);

        $req = new AuthoritativeAuditAdminQueryRequestDTO();
        $res = $this->repository->paginate($req);

        // Admin Query should NOT read from outbox
        $this->assertSame(0, $res->total);
        $this->assertCount(0, $res->items);
    }

    private function insertLog(
        string $eventId,
        ?string $actorType,
        ?int $actorId,
        string $action,
        ?string $targetType,
        ?int $targetId,
        ?string $changes,
        ?string $correlationId,
        string $occurredAt
    ): void {
        $pdo = $this->pdo;
        if ($pdo === null) {
            $this->fail('PDO not initialized.');
        }
        $stmt = $pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId, $actorType, $actorId, $action, $targetType, $targetId, $changes, $correlationId, $occurredAt
        ]);
    }
}
