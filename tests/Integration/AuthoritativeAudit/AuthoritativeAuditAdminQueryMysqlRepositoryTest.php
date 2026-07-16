<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository
 */
final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends MysqlIntegrationTestCase
{
    private AuthoritativeAuditAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo !== null) {
            $this->repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->pdo);
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

    public function testPaginateEmptyResult(): void
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('PDO not initialized.');
        }

        $request = new AuthoritativeAuditAdminQueryRequestDTO();
        $result = $this->repository->paginate($request);

        $this->assertCount(0, $result);
        $this->assertSame(0, $result->total);
        $this->assertSame(0, $result->filtered);
    }

    public function testPaginateWithData(): void
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('PDO not initialized.');
        }

        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute(['evt-1', 'admin', 1, 'action-1', 'target', 1, '{"k":"v"}', 'corr-1', $now->format('Y-m-d H:i:s.u')]);
        $stmt->execute(['evt-2', 'user', 2, 'action-2', 'target', 2, '{"k":"v"}', 'corr-2', $now->format('Y-m-d H:i:s.u')]);

        $request = new AuthoritativeAuditAdminQueryRequestDTO(
            actorType: 'admin'
        );

        $result = $this->repository->paginate($request);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result->total);
        $this->assertSame(1, $result->filtered);
        $this->assertSame('evt-1', $result->items[0]->eventId);
        $this->assertSame(['k' => 'v'], $result->items[0]->changes);
    }
}
