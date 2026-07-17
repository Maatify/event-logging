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
        if ($this->pdo === null) {
            $this->fail('Integration tests require a real MySQL database.');
        }
        /** @var \PDO $pdo */
        $pdo = $this->pdo;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->assertFalse((bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
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
        /** @var \PDO $pdo */
        $pdo = $this->pdo;
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



    public function testCursorPagination(): void
    {
        $this->writer->write(new AuthoritativeAuditOutboxWriteDTO('evt-1', 'sys', 1, 'act', 'tgt', 1, 'LOW', ['old' => 'a'], 'corr-1', new DateTimeImmutable('2024-01-01 10:00:00')));
        $this->writer->write(new AuthoritativeAuditOutboxWriteDTO('evt-2', 'sys', 2, 'act', 'tgt', 2, 'LOW', ['old' => 'b'], 'corr-2', new DateTimeImmutable('2024-01-01 10:00:00')));
        $this->writer->write(new AuthoritativeAuditOutboxWriteDTO('evt-3', 'sys', 3, 'act', 'tgt', 3, 'LOW', ['old' => 'c'], 'corr-3', new DateTimeImmutable('2024-01-01 10:00:00')));

        /** @var \PDO $pdo */
        $pdo = $this->pdo;
        $pdo->exec('INSERT INTO maa_event_logging_authoritative_audit_log (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at) SELECT event_id, actor_type, actor_id, action, target_type, target_id, payload, correlation_id, created_at FROM maa_event_logging_authoritative_audit_outbox');

        $q1 = new AuthoritativeAuditQueryDTO(limit: 1);
        $res1 = $this->query->find($q1);
        $this->assertCount(1, $res1);
        $this->assertSame('evt-3', $res1[0]->eventId);

        $q2 = new AuthoritativeAuditQueryDTO(cursorOccurredAt: $res1[0]->occurredAt, cursorId: $res1[0]->id, limit: 1);
        $res2 = $this->query->find($q2);
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        $q3 = new AuthoritativeAuditQueryDTO(cursorOccurredAt: $res2[0]->occurredAt, cursorId: $res2[0]->id, limit: 1);
        $res3 = $this->query->find($q3);
        $this->assertCount(1, $res3);
        $this->assertSame('evt-1', $res3[0]->eventId);

        $q4 = new AuthoritativeAuditQueryDTO(cursorOccurredAt: $res3[0]->occurredAt, cursorId: $res3[0]->id, limit: 1);
        $res4 = $this->query->find($q4);
        $this->assertEmpty($res4);
    }

    public function testPrimitiveRepositoryDoesNotOwnTransactions(): void
    {
        /** @var \PDO $pdo */
        $pdo = $this->pdo;
        $this->assertFalse($pdo->inTransaction());
        $this->query->find(new AuthoritativeAuditQueryDTO());
        $this->assertFalse($pdo->inTransaction());

        $pdo->beginTransaction();
        $this->assertTrue($pdo->inTransaction());
        $this->query->find(new AuthoritativeAuditQueryDTO());
        $this->assertTrue($pdo->inTransaction());
        $pdo->rollBack();
        $this->assertFalse($pdo->inTransaction());
    }

    private function simulateOutboxConsumer(AuthoritativeAuditOutboxWriteDTO $dto): void
    {
        if ($this->pdo === null) {
            $this->fail('PDO not initialized.');
        }
        /** @var \PDO $pdo */
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
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
