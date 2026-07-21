<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository
 */
final class AuthoritativeAuditRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AuthoritativeAuditOutboxWriterMysqlRepository $writer;
    private AuthoritativeAuditQueryMysqlRepository $query;

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
        $this->writer = new AuthoritativeAuditOutboxWriterMysqlRepository($this->pdo);
        $this->query = new AuthoritativeAuditQueryMysqlRepository($this->pdo);
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

    public function testWriteAndQueryRoundtrip(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $writeDto = new AuthoritativeAuditOutboxWriteDTO(
            eventId: 'event-123',
            actorType: 'admin',
            actorId: 42,
            action: 'update_user',
            targetType: 'user',
            targetId: 100,
            riskLevel: 'HIGH',
            payload: ['old_name' => 'John', 'new_name' => 'Jane'],
            correlationId: 'corr-xyz',
            createdAt: $now
        );

        $this->writer->write($writeDto);

        // To test query, we need to populate the log table (simulating outbox consumer)
        $this->simulateOutboxConsumer($writeDto);

        $queryDto = new AuthoritativeAuditQueryDTO(
            actorType: 'admin',
            actorId: 42,
            action: 'update_user'
        );

        $results = $this->query->find($queryDto);
        $this->assertCount(1, $results);

        $viewDto = $results[0];
        $this->assertSame('event-123', $viewDto->eventId);
        $this->assertSame('admin', $viewDto->actorType);
        $this->assertSame(42, $viewDto->actorId);
        $this->assertSame('update_user', $viewDto->action);
        $this->assertSame('user', $viewDto->targetType);
        $this->assertSame(100, $viewDto->targetId);
        $this->assertEquals(['old_name' => 'John', 'new_name' => 'Jane'], $viewDto->changes); // Note: we map payload to changes here for testing roundtrip
        $this->assertSame('corr-xyz', $viewDto->correlationId);
        $this->assertEquals($now, $viewDto->occurredAt);
    }

    public function testCorruptJsonMapsToNullSafely(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        // MySQL 5.7+ / MariaDB enforce JSON constraint on JSON columns.
        // To test corrupt JSON handling *at the repository read layer*, we need to disable the check temporarily
        // or bypass it. MariaDB uses CHECK(json_valid(`changes`)). We can temporarily drop this check constraint
        // just for this test to prove the PHP code handles corrupt JSON.


        $serverVersionAttr = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $serverVersion = is_scalar($serverVersionAttr) ? (string) $serverVersionAttr : '';
        $isMariaDb = str_contains($serverVersion, 'MariaDB');

        if ($isMariaDb) {
            $this->pdo->exec("ALTER TABLE maa_event_logging_authoritative_audit_log DROP CONSTRAINT IF EXISTS `changes`");
        }

        try {
            // Insert corrupt JSON directly into log table
            $stmt = $this->pdo->prepare("
                INSERT INTO maa_event_logging_authoritative_audit_log
                (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
                VALUES (?, ?, ?, ?, ?, ?, 'invalid-json', ?, ?)
            ");

            $stmt->execute([
                'event-corrupt',
                'system',
                1,
                'test_action',
                'target',
                2,
                'corr-1',
                $now->format('Y-m-d H:i:s.u')
            ]);
        } catch (\PDOException $e) {
            // If we still can't insert invalid JSON, the DB is strictly enforcing it (e.g. MySQL 8).
            // This means corrupt JSON is practically impossible at the DB level, but we can skip if we can't test it.
            $this->markTestSkipped("Database enforces strict JSON validity: " . $e->getMessage());
        }

        $queryDto = new AuthoritativeAuditQueryDTO(
            action: 'test_action'
        );

        $results = $this->query->find($queryDto);
        $this->assertCount(1, $results);

        $viewDto = $results[0];
        $this->assertSame('event-corrupt', $viewDto->eventId);
        // Assert corrupt JSON is safely ignored (mapped to null or empty depending on implementation, here usually null)
        $this->assertNull($viewDto->changes);
    }

    public function testCursorPagination(): void
    {
        $now1 = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $now2 = new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC'));

        $dto1 = new AuthoritativeAuditOutboxWriteDTO('evt-1', 'admin', 1, 'action', 'tgt', 1, 'LOW', [], 'corr', $now1);
        $dto2 = new AuthoritativeAuditOutboxWriteDTO('evt-2', 'admin', 1, 'action', 'tgt', 1, 'LOW', [], 'corr', $now2);
        $dto3 = new AuthoritativeAuditOutboxWriteDTO('evt-3', 'admin', 1, 'action', 'tgt', 1, 'LOW', [], 'corr', $now2); // Same timestamp as dto2

        $this->simulateOutboxConsumer($dto1);
        $this->simulateOutboxConsumer($dto2);
        $this->simulateOutboxConsumer($dto3); // This will have a higher auto-increment ID than dto2

        // Query limit 1
        $query1 = new AuthoritativeAuditQueryDTO(limit: 1);
        $res1 = $this->query->find($query1);
        $this->assertCount(1, $res1);
        $this->assertSame('evt-3', $res1[0]->eventId); // Ordered by time desc, id desc (evt-3 is latest ID among same time)

        // Page 2
        $query2 = new AuthoritativeAuditQueryDTO(
            cursorOccurredAt: $res1[0]->occurredAt,
            cursorId: $res1[0]->id,
            limit: 1
        );
        $res2 = $this->query->find($query2);
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        // Page 3
        $query3 = new AuthoritativeAuditQueryDTO(
            cursorOccurredAt: $res2[0]->occurredAt,
            cursorId: $res2[0]->id,
            limit: 1
        );
        $res3 = $this->query->find($query3);
        $this->assertCount(1, $res3);
        $this->assertSame('evt-1', $res3[0]->eventId);
    }

    private function simulateOutboxConsumer(AuthoritativeAuditOutboxWriteDTO $dto): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $dto->eventId,
            $dto->actorType,
            $dto->actorId,
            $dto->action,
            $dto->targetType,
            $dto->targetId,
            json_encode($dto->payload),
            $dto->correlationId,
            $dto->createdAt->format('Y-m-d H:i:s.u')
        ]);
    }
}
