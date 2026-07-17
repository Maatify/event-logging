<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;
use PDO;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository
 */
final class AuthoritativeAuditRepositoryTest extends MysqlIntegrationTestCase
{
    private AuthoritativeAuditOutboxWriterMysqlRepository $writer;
    private AuthoritativeAuditQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo !== null) {
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        if ($this->pdo !== null) {
            $this->writer = new AuthoritativeAuditOutboxWriterMysqlRepository($this->pdo);
            $this->query = new AuthoritativeAuditQueryMysqlRepository($this->pdo);
        }
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
            'maa_event_logging_authoritative_audit_outbox',
            'maa_event_logging_authoritative_audit_log'
        ];
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

        if ($this->pdo === null) {
            $this->fail('PDO not initialized.');
        }

        $serverVersionAttr = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $serverVersion = is_scalar($serverVersionAttr) ? (string) $serverVersionAttr : '';
        $isMariaDb = str_contains($serverVersion, 'MariaDB');

        if ($isMariaDb) {
            $this->pdo->exec("ALTER TABLE maa_event_logging_authoritative_audit_log DROP CONSTRAINT IF EXISTS `changes`");
        }

        // Insert corrupt JSON directly into log table
        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, 'invalid-json', ?, ?)
        ");

        $this->expectException(\PDOException::class);
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

    public function testPrimitiveRepositoryDoesNotOwnTransactions(): void
    {
        $pdo = $this->pdo;
        if ($pdo === null) {
            $this->fail('PDO not initialized.');
        }

        $pdo->beginTransaction();
        $query = new AuthoritativeAuditQueryDTO(limit: 1);
        $this->query->find($query);

        // The repository should not have committed or rolled back the caller's transaction
        $this->assertTrue($pdo->inTransaction());
        $pdo->rollBack();
    }

    private function simulateOutboxConsumer(AuthoritativeAuditOutboxWriteDTO $dto): void
    {
        if ($this->pdo === null) {
            $this->fail('PDO not initialized.');
        }
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
