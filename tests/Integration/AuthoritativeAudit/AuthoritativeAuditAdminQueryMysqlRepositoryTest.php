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

    public function testFiltersIndependentlyAndCombinedAndSortTieBreaker(): void
    {
        $this->insertLog('evt-filter', 'ActT', 10, 'ActionA', 'TgtT', 20, null, 'Corr-A', '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'ActT2', 10, 'ActionB', 'TgtT2', 20, null, 'Corr-B', '2025-01-01 10:00:00.000000');

        $this->assertCount(1, $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-filter'))->items);
        $this->assertCount(1, $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'ActT'))->items);
        $this->assertCount(2, $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(actorId: 10))->items);
        $this->assertCount(1, $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'TgtT'))->items);
        $this->assertCount(2, $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(targetId: 20))->items);
        $this->assertCount(1, $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(action: 'ActionA'))->items);
        $this->assertCount(1, $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(correlationId: 'Corr-A'))->items);

        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_authoritative_audit_log');

        $this->insertLog('evt-1', actorType: 'A', actorId: 1, action: 'act1', targetType: 'T1', targetId: 1, correlationId: 'C1', occurredAt: '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-2', actorType: 'A', actorId: 1, action: 'act1', targetType: 'T1', targetId: 1, correlationId: 'C1', occurredAt: '2025-01-01 10:00:00.000000');
        $this->insertLog('evt-3', actorType: 'B', actorId: 2, action: 'act2', targetType: 'T2', targetId: 2, correlationId: 'C2', occurredAt: '2025-01-01 10:00:00.000000');

        $reqCombined = new AuthoritativeAuditAdminQueryRequestDTO(
            eventId: 'evt-1',
            actorType: 'A',
            actorId: 1,
            action: 'act1',
            targetType: 'T1',
            targetId: 1,
            correlationId: 'C1',
        );
        $resCombined = $this->repository->paginate($reqCombined);
        $this->assertSame(3, $resCombined->total);
        $this->assertSame(1, $resCombined->filtered);
        $this->assertSame('evt-1', $resCombined->items[0]->eventId);

        $reqDesc = new AuthoritativeAuditAdminQueryRequestDTO(sortDirection: 'DESC');
        $resDesc = $this->repository->paginate($reqDesc);
        $this->assertCount(3, $resDesc->items);
        $this->assertSame('evt-3', $resDesc->items[0]->eventId);
        $this->assertSame('evt-2', $resDesc->items[1]->eventId);
        $this->assertSame('evt-1', $resDesc->items[2]->eventId);
    }

    public function testDateBoundariesAndPageNormalization(): void
    {
        $this->insertLog('evt-old', occurredAt: '2025-01-01 14:59:59.999999');
        $this->insertLog('evt-exact-start', occurredAt: '2025-01-01 15:00:00.000000');
        $this->insertLog('evt-exact-end', occurredAt: '2025-01-01 16:00:00.000000');
        $this->insertLog('evt-future', occurredAt: '2025-01-01 16:00:00.000001');

        $req = new AuthoritativeAuditAdminQueryRequestDTO(
            after: new DateTimeImmutable('2025-01-01 10:00:00.000000', new DateTimeZone('America/New_York')),
            before: new DateTimeImmutable('2025-01-01 11:00:00.000000', new DateTimeZone('America/New_York')),
            sortBy: 'occurred_at',
            sortDirection: 'ASC'
        );

        $res = $this->repository->paginate($req);
        $this->assertCount(2, $res->items);
        $this->assertSame('evt-exact-start', $res->items[0]->eventId);
        $this->assertSame('evt-exact-end', $res->items[1]->eventId);

        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_authoritative_audit_log');
        for ($i = 1; $i <= 205; $i++) {
            $this->insertLog('evt-'.$i, occurredAt: '2025-01-01 15:00:00.000000');
        }

        $reqClamp = new AuthoritativeAuditAdminQueryRequestDTO(page: 0, perPage: 500, sortDirection: 'ASC');
        $resClamp = $this->repository->paginate($reqClamp);

        $this->assertSame(1, $resClamp->page);
        $this->assertSame(200, $resClamp->perPage);
        $this->assertSame(205, $resClamp->total);
        $this->assertSame(2, $resClamp->totalPages);
        $this->assertCount(200, $resClamp->items);
        $this->assertTrue($resClamp->hasNext);
        $this->assertFalse($resClamp->hasPrevious);
        $this->assertSame('evt-205', $resClamp->items[0]->eventId);

        $reqPage2 = new AuthoritativeAuditAdminQueryRequestDTO(page: 2, perPage: 500, sortDirection: 'ASC');
        $resPage2 = $this->repository->paginate($reqPage2);

        $this->assertSame(2, $resPage2->page);
        $this->assertSame(200, $resPage2->perPage);
        $this->assertCount(5, $resPage2->items);
        $this->assertFalse($resPage2->hasNext);
        $this->assertTrue($resPage2->hasPrevious);
        $this->assertSame('evt-5', $resPage2->items[0]->eventId);
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

    public function testNullableHydrationAndExactOccurredAt(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'evt-nulls',
            'System',
            null,
            'act',
            'Tgt',
            null,
            null,
            null,
            null,
            null,
            '2025-01-01 10:00:00.123456'
        ]);

        $res = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
        $this->assertCount(1, $res->items);
        $item = $res->items[0];

        $this->assertNull($item->actorId);
        $this->assertNull($item->targetId);
        $this->assertNull($item->correlationId);
        $this->assertNull($item->changes);
        $this->assertNull($item->ipAddress);
        $this->assertNull($item->userAgent);
        $this->assertSame('System', $item->actorType);
        $this->assertSame('Tgt', $item->targetType);
        $this->assertSame('act', $item->action);
        $this->assertSame('2025-01-01 10:00:00.123456', $item->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $item->occurredAt->getTimezone()->getName());
    }

    public function testPaginateHydrationFallbacks(): void
    {
        $this->pdo->exec('ALTER TABLE maa_event_logging_authoritative_audit_log MODIFY COLUMN changes LONGTEXT NULL');

        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute(['evt-1', 'system', 1, 'act', 'tgt', 1, '{"key": "value"}', 'c', '2025-01-01 10:00:01.123456']);
        $stmt->execute(['evt-2', 'system', 1, 'act', 'tgt', 1, '', 'c', '2025-01-01 10:00:02.000000']);
        $stmt->execute(['evt-3', 'system', 1, 'act', 'tgt', 1, null, 'c', '2025-01-01 10:00:03.000000']);
        $stmt->execute(['evt-4', 'system', 1, 'act', 'tgt', 1, 'invalid-json', 'c', '2025-01-01 10:00:04.000000']);

        $request = new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'occurred_at', sortDirection: 'ASC');
        $result = $this->repository->paginate($request);

        $this->assertCount(4, $result->items);
        $this->assertSame(['key' => 'value'], $result->items[0]->changes);
        $this->assertNull($result->items[1]->changes);
        $this->assertNull($result->items[2]->changes);
        $this->assertNull($result->items[3]->changes);

        $this->assertSame('2025-01-01 10:00:01.123456', $result->items[0]->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $result->items[0]->occurredAt->getTimezone()->getName());
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

    public function testRealStorageFailureMapsToStorageExceptionWithPreviousThrowableAndPreservesTransaction(): void
    {
        $this->pdo->exec('DROP TABLE maa_event_logging_authoritative_audit_log');
        $this->pdo->beginTransaction();

        $exceptionThrown = false;
        try {
            $request = new AuthoritativeAuditAdminQueryRequestDTO();
            $this->repository->paginate($request);
        } catch (AuthoritativeAuditStorageException $e) {
            $exceptionThrown = true;
            $this->assertStringStartsWith('Failed to query AuthoritativeAudit records:', $e->getMessage());
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }

        $this->assertTrue($exceptionThrown, 'Storage exception must be thrown');
        $this->assertTrue($this->pdo->inTransaction(), 'Caller transaction must be preserved after storage failure');
        $this->pdo->rollBack();
    }
}
