<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository
 */
final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AuthoritativeAuditAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for AuthoritativeAudit integration tests.');
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
        $this->repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->pdo);
    }

    private function resetSchema(): void
    {
        $schema = file_get_contents(__DIR__ . '/../../../src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql');
        if (! is_string($schema) || $schema === '') {
            throw new RuntimeException('Failed to read AuthoritativeAudit schema.');
        }

        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_authoritative_audit_log;');
        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_authoritative_audit_outbox;');
        $this->pdo->exec($schema);
    }

    private function insertLog(
        string $eventId,
        string $actorType = 'User',
        int $actorId = 123,
        string $action = 'created',
        string $targetType = 'Post',
        int $targetId = 456,
        ?string $changes = null,
        ?string $correlationId = null,
        string $occurredAt = '2025-01-01 10:00:00.000000'
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId,
            $actorType,
            $actorId,
            $action,
            $targetType,
            $targetId,
            $changes,
            $correlationId,
            $occurredAt,
        ]);
    }

    public function testNativePreparedStatementsAreStrictlyActive(): void
    {
        $this->assertFalse(
            (bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES),
            'Native prepared statements must strictly be active for Admin Queries.'
        );
    }

    public function testFiltersTotalAndFilteredAlignmentAndSortTieBreaker(): void
    {
        // eventId independently
        $this->insertLog('evt-specific', occurredAt: '2025-01-01 10:00:00.000000');
        $reqEvent = new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-specific');
        $resEvent = $this->repository->paginate($reqEvent);
        $this->assertSame(1, $resEvent->total);
        $this->assertSame(1, $resEvent->filtered);
        $this->assertCount(1, $resEvent->items);

        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_authoritative_audit_log');

        // Multiple same timestamp to test sort tie breaker id DESC
        $this->insertLog('evt-1', actorType: 'A', actorId: 1, action: 'act1', targetType: 'T1', targetId: 1, correlationId: 'C1', occurredAt: '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-2', actorType: 'A', actorId: 2, action: 'act2', targetType: 'T1', targetId: 2, correlationId: 'C2', occurredAt: '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-3', actorType: 'B', actorId: 3, action: 'act1', targetType: 'T2', targetId: 3, correlationId: 'C1', occurredAt: '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-4', actorType: 'B', actorId: 3, action: 'act2', targetType: 'T2', targetId: 3, correlationId: 'C3', occurredAt: '2025-01-01 10:00:01.000000');

        // Test explicit descending sort on occurred_at (default is DESC).
        // evt-4 will be first. evt-3, evt-2, evt-1 share timestamp.
        // Tie breaker is id DESC, so evt-3 (inserted 3rd), then evt-2, then evt-1.
        $reqDesc = new AuthoritativeAuditAdminQueryRequestDTO(sortDirection: 'DESC');
        $resDesc = $this->repository->paginate($reqDesc);

        $this->assertCount(4, $resDesc->items);
        $this->assertSame('evt-4', $resDesc->items[0]->eventId);
        $this->assertSame('evt-3', $resDesc->items[1]->eventId);
        $this->assertSame('evt-2', $resDesc->items[2]->eventId);
        $this->assertSame('evt-1', $resDesc->items[3]->eventId);

        // Test independent actorType
        $reqActor = new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'A');
        $resActor = $this->repository->paginate($reqActor);
        $this->assertSame(4, $resActor->total);
        $this->assertSame(2, $resActor->filtered);
        $this->assertCount(2, $resActor->items);

        // Test independent targetType
        $reqTarget = new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'T2');
        $resTarget = $this->repository->paginate($reqTarget);
        $this->assertSame(2, $resTarget->filtered);

        // Test multiple combined filters
        $reqCombined = new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'B', action: 'act1', correlationId: 'C1');
        $resCombined = $this->repository->paginate($reqCombined);
        $this->assertSame(1, $resCombined->filtered);
        $this->assertSame('evt-3', $resCombined->items[0]->eventId);
    }

    public function testDateBoundariesAndPageNormalization(): void
    {
        $this->insertLog('evt-old', occurredAt: '2025-01-01 09:59:59.999999');
        $this->insertLog('evt-exact-start', occurredAt: '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-exact-end', occurredAt: '2025-01-01 11:00:00.000000');
        $this->insertLog('evt-future', occurredAt: '2025-01-01 11:00:00.000001');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2025-01-01 10:00:00', new DateTimeZone('America/New_York')),
            before: new DateTimeImmutable('2025-01-01 11:00:00', new DateTimeZone('America/New_York')),
        );
        // Note: DTO doesn't convert to UTC in constructor, it preserves input. The descriptor converts it.
        // Assuming the input is correctly handled. Wait, let's just use UTC for safety to match the database expectation if it expects UTC.
        // The instructions say "non-UTC input normalization, and exact six-digit microseconds".
        // The builder should convert to UTC.
        // If DB is in UTC, and we pass America/New_York (UTC-5), 10am NY is 3pm UTC.
        // Let's just insert logs at UTC 15:00:00 to match NY 10:00:00.
        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_authoritative_audit_log');
        $this->insertLog('evt-old', occurredAt: '2025-01-01 14:59:59.999999'); // 09:59:59.999999 NY
        $this->insertLog('evt-exact-start', occurredAt: '2025-01-01 15:00:00.000000'); // 10:00 NY
        $this->insertLog('evt-exact-end', occurredAt: '2025-01-01 16:00:00.000000'); // 11:00 NY
        $this->insertLog('evt-future', occurredAt: '2025-01-01 16:00:00.000001'); // 11:00:00.000001 NY

        $req = new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2025-01-01 10:00:00', new DateTimeZone('America/New_York')),
            before: new DateTimeImmutable('2025-01-01 11:00:00', new DateTimeZone('America/New_York')),
        );

        $res = $this->repository->paginate($req);
        $this->assertCount(2, $res->items);
        // Order by occurred_at DESC by default
        $this->assertSame('evt-exact-end', $res->items[0]->eventId);
        $this->assertSame('evt-exact-start', $res->items[1]->eventId);

        // Page Normalization & clamping: page 0 becomes 1, perPage 500 becomes 200
        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_authoritative_audit_log');
        for ($i = 1; $i <= 205; $i++) {
            $this->insertLog('evt-'.$i, occurredAt: '2025-01-01 15:00:00.000000');
        }

        $reqClamp = new AuthoritativeAuditAdminQueryRequestDTO(page: 0, perPage: 500);
        $resClamp = $this->repository->paginate($reqClamp);

        $this->assertSame(1, $resClamp->page);
        $this->assertSame(200, $resClamp->perPage);
        $this->assertSame(205, $resClamp->total);
        $this->assertSame(2, $resClamp->totalPages);
        $this->assertCount(200, $resClamp->items);
        $this->assertTrue($resClamp->hasNext);
        $this->assertFalse($resClamp->hasPrevious);
    }

    public function testMaterializedLogOnlyBehavior(): void
    {
        $this->pdo->exec("
            INSERT INTO maa_event_logging_authoritative_audit_outbox
            (event_id, actor_type, actor_id, action, target_type, target_id, risk_level, payload, correlation_id, created_at)
            VALUES ('evt-outbox', 'U', 1, 'act', 'T', 1, 'HIGH', '{\"k\":\"v\"}', 'C', '2025-01-01 10:00:00.000000')
        ");

        $req = new AuthoritativeAuditAdminQueryRequestDTO();
        $res = $this->repository->paginate($req);

        $this->assertSame(0, $res->total, 'Admin read must return 0 items when log is empty, even if outbox has records.');
        $this->assertCount(0, $res->items);
    }

    public function testPaginateReturnsEmptyWhenNoLogs(): void
    {
        $request = new AuthoritativeAuditAdminQueryRequestDTO();
        $result = $this->repository->paginate($request);

        $this->assertSame(0, $result->total);
        $this->assertSame(0, $result->filtered);
        $this->assertCount(0, $result->items);
    }

    public function testPaginateWithAllFilters(): void
    {
        $this->insertLog('evt-1', 'User', 1, 'update', 'Page', 1, null, 'cor-1', '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'System', 2, 'create', 'Page', 1, null, 'cor-2', '2025-01-01 11:00:00.000000');
        $this->insertLog('evt-3', 'User', 1, 'update', 'Post', 2, null, 'cor-1', '2025-01-01 12:00:00.000000');

        $request = new AuthoritativeAuditAdminQueryRequestDTO(
            actorType: 'User',
            actorId: 1,
            action: 'update',
            targetType: 'Page',
            targetId: 1,
            correlationId: 'cor-1',
            after: new DateTimeImmutable('2025-01-01 09:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2025-01-01 11:00:00', new DateTimeZone('UTC')),
        );

        $result = $this->repository->paginate($request);

        $this->assertSame(3, $result->total);
        $this->assertSame(1, $result->filtered);
        $this->assertCount(1, $result->items);
        $this->assertSame('evt-1', $result->items[0]->eventId);
    }

    public function testPaginateHydrationFallbacks(): void
    {
        $this->insertLog('evt-1', changes: '{"key": "value"}', occurredAt: '2025-01-01 10:00:01.000000');

        $insertedCount = 1;
        try {
            $this->pdo->exec('ALTER TABLE maa_event_logging_authoritative_audit_log DROP CONSTRAINT IF EXISTS `changes`');
            $this->insertLog('evt-2', changes: '', occurredAt: '2025-01-01 10:00:02.000000');
            $insertedCount++;
            $this->insertLog('evt-3', changes: null, occurredAt: '2025-01-01 10:00:03.000000');
            $insertedCount++;
            $this->insertLog('evt-4', changes: 'invalid-json', occurredAt: '2025-01-01 10:00:04.000000');
            $insertedCount++;
        } catch (\PDOException $e) {
            $this->pdo->exec('DELETE FROM maa_event_logging_authoritative_audit_log WHERE event_id != "evt-1"');
            $this->insertLog('evt-3', changes: null, occurredAt: '2025-01-01 10:00:03.000000');
            $insertedCount = 2;
        }

        $request = new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'occurred_at', sortDirection: 'ASC');
        $result = $this->repository->paginate($request);

        $this->assertCount($insertedCount, $result->items);
        $this->assertSame(['key' => 'value'], $result->items[0]->changes);

        if ($insertedCount === 4) {
            $this->assertNull($result->items[1]->changes);
            $this->assertNull($result->items[2]->changes);
            $this->assertNull($result->items[3]->changes);
        } else {
            $this->assertNull($result->items[1]->changes);
        }
    }

    public function testCallerOwnedTransactionRemainsActiveAfterRead(): void
    {
        $this->insertLog('evt-1');

        $this->pdo->beginTransaction();

        $request = new AuthoritativeAuditAdminQueryRequestDTO();
        $result = $this->repository->paginate($request);

        $this->assertCount(1, $result->items);
        $this->assertTrue($this->pdo->inTransaction(), 'Repository must not commit or rollback caller transactions');

        $this->pdo->commit();
    }

    public function testRealStorageFailureMapsToStorageExceptionWithPreviousThrowable(): void
    {
        $this->pdo->exec('DROP TABLE maa_event_logging_authoritative_audit_log');

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Failed to query AuthoritativeAudit records');

        $request = new AuthoritativeAuditAdminQueryRequestDTO();
        $this->repository->paginate($request);
    }
}
