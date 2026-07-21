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
