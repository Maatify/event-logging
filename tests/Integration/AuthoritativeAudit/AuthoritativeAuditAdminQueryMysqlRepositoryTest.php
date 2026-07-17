<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use PDO;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository
 */
final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends TestCase
{
    private AuthoritativeAuditAdminQueryMysqlRepository $repository;

        private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (!$dsn) {
            throw new RuntimeException('Integration tests require a real MySQL database. EVENT_LOGGING_TEST_MYSQL_DSN is missing.');
        }

        $user = getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '';

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->setUpSchema();
        $this->cleanTables();

        $this->repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->cleanTables();
        }
        parent::tearDown();
    }

    private function setUpSchema(): void
    {
        $file = __DIR__ . '/../../../src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql';
        if (!file_exists($file)) {
            throw new RuntimeException("Schema file not found: " . $file);
        }

        $sql = file_get_contents($file);

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec("DROP TABLE IF EXISTS `maa_event_logging_authoritative_audit_log`");
        $this->pdo->exec("DROP TABLE IF EXISTS `maa_event_logging_authoritative_audit_outbox`");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $statements = [];
        $current = '';
        $lines = explode("\n", (string) $sql);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '--')) continue;
            $current .= $line . "\n";
            if (str_ends_with(trim($line), ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }

        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $this->pdo->exec($stmt);
            }
        }
    }

    private function cleanTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec("TRUNCATE TABLE `maa_event_logging_authoritative_audit_log`");
        $this->pdo->exec("TRUNCATE TABLE `maa_event_logging_authoritative_audit_outbox`");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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

    public function testDateBoundariesAreInclusiveAndEqualBoundariesAreValid(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act', 'tgt', 1, null, 'c1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'sys', 1, 'act', 'tgt', 1, null, 'c2', '2024-01-01 10:00:00.500000');
        $this->insertLog('evt-3', 'sys', 1, 'act', 'tgt', 1, null, 'c3', '2024-01-01 11:00:00.000000');

        $tz = new DateTimeZone('Europe/London'); // Will be converted to UTC internally
        $time1 = new DateTimeImmutable('2024-01-01 10:00:00.000000', new DateTimeZone('UTC'));
        $time2 = new DateTimeImmutable('2024-01-01 10:00:00.500000', new DateTimeZone('UTC'));

        $req1 = new AuthoritativeAuditAdminQueryRequestDTO(
            after: $time1->setTimezone($tz),
            before: $time2->setTimezone($tz)
        );

        $res1 = $this->repository->paginate($req1);
        $this->assertSame(2, $res1->filtered);
        $this->assertSame('evt-2', $res1->items[0]->eventId);
        $this->assertSame('evt-1', $res1->items[1]->eventId);

        $req2 = new AuthoritativeAuditAdminQueryRequestDTO(
            after: $time1->setTimezone($tz),
            before: $time1->setTimezone($tz)
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
        $this->assertSame(1, $res->page); // Normalized
        $this->assertSame(0, $res->totalPages);
    }

    public function testFirstAndLaterPagesPerPageClampRequestedSortAndTieBreaker(): void
    {
        // Inserting 5 items with the same time to test tie-breaker (id DESC)
        $time = '2024-01-01 10:00:00.000000';
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog("evt-$i", 'sys', 1, 'act', 'tgt', 1, null, 'c', $time);
        }

        // Items inserted 1..5. IDs are 1..5. Sort is time DESC, id DESC. Order will be evt-5, evt-4, evt-3, evt-2, evt-1.

        $req1 = new AuthoritativeAuditAdminQueryRequestDTO(page: 1, perPage: 2, sortBy: 'occurred_at', sortDirection: 'asc');
        $res1 = $this->repository->paginate($req1);

        $this->assertSame(2, $res1->perPage);
        $this->assertCount(2, $res1->items);
        $this->assertSame('evt-5', $res1->items[0]->eventId); // Time is same, ID DESC tie-breaker wins
        $this->assertSame('evt-4', $res1->items[1]->eventId);

        $req2 = new AuthoritativeAuditAdminQueryRequestDTO(page: 2, perPage: 2, sortBy: 'occurred_at', sortDirection: 'asc');
        $res2 = $this->repository->paginate($req2);
        $this->assertSame(2, $res2->perPage);
        $this->assertCount(2, $res2->items);
        $this->assertSame('evt-3', $res2->items[0]->eventId);
        $this->assertSame('evt-2', $res2->items[1]->eventId);

        // Clamping min/max
        $req3 = new AuthoritativeAuditAdminQueryRequestDTO(perPage: 0);
        $res3 = $this->repository->paginate($req3);
        $this->assertSame(1, $res3->perPage); // Min 1

        $req4 = new AuthoritativeAuditAdminQueryRequestDTO(perPage: 500);
        $res4 = $this->repository->paginate($req4);
        $this->assertSame(200, $res4->perPage); // Max 200
    }

        public function testActionOnlyFilter(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt1', 1, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'user', 1, 'act-2', 'tgt2', 2, null, 'corr-2', '2024-01-01 11:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(action: 'act-1');
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-1', $res->items[0]->eventId);
    }

    public function testCorrelationIdOnlyFilter(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt1', 1, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'user', 1, 'act-2', 'tgt2', 2, null, 'corr-2', '2024-01-01 11:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(correlationId: 'corr-2');
        $res = $this->repository->paginate($req);
        $this->assertSame(1, $res->filtered);
        $this->assertSame('evt-2', $res->items[0]->eventId);
    }

    public function testActorOnlyFiltersAndCombinedFilters(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt1', 1, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'user', 1, 'act-2', 'tgt2', 2, null, 'corr-2', '2024-01-01 11:00:00.000000');
        $this->insertLog('evt-3', 'sys', 2, 'act-3', 'tgt3', 3, null, 'corr-3', '2024-01-01 12:00:00.000000');

        $reqType = new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'sys');
        $resType = $this->repository->paginate($reqType);
        $this->assertSame(3, $resType->total);
        $this->assertSame(2, $resType->filtered);
        $this->assertCount(2, $resType->items);
        $this->assertSame('evt-3', $resType->items[0]->eventId);
        $this->assertSame('evt-1', $resType->items[1]->eventId);

        $reqId = new AuthoritativeAuditAdminQueryRequestDTO(actorId: 1);
        $resId = $this->repository->paginate($reqId);
        $this->assertSame(3, $resId->total);
        $this->assertSame(2, $resId->filtered);
        $this->assertCount(2, $resId->items);
        $this->assertSame('evt-2', $resId->items[0]->eventId);
        $this->assertSame('evt-1', $resId->items[1]->eventId);

        $reqCombined = new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'sys', actorId: 1);
        $resCombined = $this->repository->paginate($reqCombined);
        $this->assertSame(3, $resCombined->total);
        $this->assertSame(1, $resCombined->filtered);
        $this->assertCount(1, $resCombined->items);
        $this->assertSame('evt-1', $resCombined->items[0]->eventId);
    }

    public function testTargetOnlyFiltersAndCombinedFilters(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt1', 1, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'sys', 1, 'act-1', 'tgt1', 2, null, 'corr-2', '2024-01-01 11:00:00.000000');
        $this->insertLog('evt-3', 'sys', 1, 'act-1', 'tgt2', 1, null, 'corr-3', '2024-01-01 12:00:00.000000');

        $reqType = new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'tgt1');
        $resType = $this->repository->paginate($reqType);
        $this->assertSame(3, $resType->total);
        $this->assertSame(2, $resType->filtered);
        $this->assertCount(2, $resType->items);
        $this->assertSame('evt-2', $resType->items[0]->eventId);
        $this->assertSame('evt-1', $resType->items[1]->eventId);

        $reqId = new AuthoritativeAuditAdminQueryRequestDTO(targetId: 1);
        $resId = $this->repository->paginate($reqId);
        $this->assertSame(3, $resId->total);
        $this->assertSame(2, $resId->filtered);
        $this->assertCount(2, $resId->items);
        $this->assertSame('evt-3', $resId->items[0]->eventId);
        $this->assertSame('evt-1', $resId->items[1]->eventId);

        $reqCombined = new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'tgt1', targetId: 1);
        $resCombined = $this->repository->paginate($reqCombined);
        $this->assertSame(3, $resCombined->total);
        $this->assertSame(1, $resCombined->filtered);
        $this->assertCount(1, $resCombined->items);
        $this->assertSame('evt-1', $resCombined->items[0]->eventId);
    }

    public function testDateConversionToUtcUsesAfricaCairo(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act', 'tgt', 1, null, 'c1', '2024-01-01 10:00:00.000000');

        $tz = new DateTimeZone('Africa/Cairo'); // UTC+2
        $time1 = new DateTimeImmutable('2024-01-01 12:00:00.000000', $tz);

        $req1 = new AuthoritativeAuditAdminQueryRequestDTO(
            after: $time1,
            before: $time1
        );

        $res1 = $this->repository->paginate($req1);
        $this->assertSame(1, $res1->filtered);
        $this->assertSame('evt-1', $res1->items[0]->eventId);
    }

    public function testJsonHydrationAndNullableColumns(): void
    {
        $this->insertLog('evt-1', null, null, 'act', null, null, '{"key": "val"}', null, '2024-01-01 10:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO();
        $res = $this->repository->paginate($req);

        $this->assertSame(1, $res->filtered);
        $item = $res->items[0];

        $this->assertNull($item->actorType);
        $this->assertNull($item->actorId);
        $this->assertNull($item->targetType);
        $this->assertNull($item->targetId);
        $this->assertNull($item->correlationId);
        $this->assertSame(['key' => 'val'], $item->changes);
    }

    public function testPaginationOverflowReturnsPage1(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 11:00:00.000000');
        $this->insertLog('evt-3', 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 12:00:00.000000');
        $this->insertLog('evt-4', 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 13:00:00.000000');
        $this->insertLog('evt-5', 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 14:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(page: 10, perPage: 2);
        $res = $this->repository->paginate($req);

        $this->assertSame(5, $res->total);
        $this->assertSame(3, $res->totalPages);
        $this->assertSame(1, $res->page);
        $this->assertFalse($res->hasPrevious);
        $this->assertTrue($res->hasNext);
        $this->assertCount(2, $res->items);
        $this->assertSame('evt-5', $res->items[0]->eventId);
        $this->assertSame('evt-4', $res->items[1]->eventId);
    }

    public function testOutboxOnlyReturnsEmpty(): void
    {
        $this->pdo->exec("
            INSERT INTO maa_event_logging_authoritative_audit_outbox
            (event_id, actor_type, actor_id, action, target_type, target_id, risk_level, payload, correlation_id, created_at)
            VALUES ('evt-out', 'sys', 1, 'act', 'tgt', 1, 'LOW', '{}', 'c', '2024-01-01 10:00:00.000000')
        ");

        $req = new AuthoritativeAuditAdminQueryRequestDTO();
        $res = $this->repository->paginate($req);

        $this->assertSame(0, $res->total);
        $this->assertSame(0, $res->filtered);
        $this->assertCount(0, $res->items);
    }

    public function testTransactionRemainsActiveInside(): void
    {
        $this->pdo->beginTransaction();
        $this->assertTrue($this->pdo->inTransaction());

        $req = new AuthoritativeAuditAdminQueryRequestDTO();
        $this->repository->paginate($req);

        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
        $this->assertFalse($this->pdo->inTransaction());
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

    public function testTransactionOwnershipRules(): void
    {
        $this->assertFalse($this->pdo->inTransaction());

        $this->pdo->beginTransaction();
        $this->insertLog('evt-txn', 'sys', 1, 'act', 'tgt', 1, null, 'c', '2024-01-01 10:00:00.000000');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-txn');
        $res = $this->repository->paginate($req);
        $this->assertCount(1, $res->items);

        $this->pdo->rollBack();

        $res2 = $this->repository->paginate($req);
        $this->assertCount(0, $res2->items);

        // Repository should not catch or alter the transaction state
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testReadingFromLogTableOnly(): void
    {
        // Insert into outbox
        $stmt = $this->pdo->prepare("
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
        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId, $actorType, $actorId, $action, $targetType, $targetId, $changes, $correlationId, $occurredAt
        ]);
    }
}
