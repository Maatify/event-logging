<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\AuthoritativeAudit\Support\StrictAuthoritativeAuditMysqlIntegrationTestCase;
use PDO;
use PDOException;
use PDOStatement;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository
 */
final class AuthoritativeAuditRepositoryTest extends StrictAuthoritativeAuditMysqlIntegrationTestCase
{
    private AuthoritativeAuditOutboxWriterMysqlRepository $writer;
    private AuthoritativeAuditQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        self::assertFalse((bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));

        $this->writer = new AuthoritativeAuditOutboxWriterMysqlRepository($this->pdo);
        $this->query = new AuthoritativeAuditQueryMysqlRepository($this->pdo);
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql';
    }

    /** @return list<string> */
    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_authoritative_audit_outbox',
            'maa_event_logging_authoritative_audit_log',
        ];
    }

    /** @throws JsonException */
    public function testWriteAndQueryRoundtrip(): void
    {
        $occurredAt = new DateTimeImmutable('2024-01-01 10:00:00.123456', new DateTimeZone('UTC'));
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
            createdAt: $occurredAt
        );

        $this->writer->write($writeDto);
        $this->materializeLog($writeDto);

        $results = $this->query->find(new AuthoritativeAuditQueryDTO(
            actorType: 'admin',
            actorId: 42,
            targetType: 'user',
            targetId: 100,
            action: 'update_user',
            correlationId: 'corr-xyz'
        ));

        self::assertCount(1, $results);
        $view = $results[0];
        self::assertSame('event-123', $view->eventId);
        self::assertSame('admin', $view->actorType);
        self::assertSame(42, $view->actorId);
        self::assertSame('update_user', $view->action);
        self::assertSame('user', $view->targetType);
        self::assertSame(100, $view->targetId);
        self::assertSame(['old_name' => 'John', 'new_name' => 'Jane'], $view->changes);
        self::assertSame('corr-xyz', $view->correlationId);
        self::assertSame('2024-01-01 10:00:00.123456', $view->occurredAt->format('Y-m-d H:i:s.u'));
        self::assertSame('UTC', $view->occurredAt->getTimezone()->getName());
    }

    public function testDatabaseRejectsMalformedJsonWithoutConstraintChanges(): void
    {
        $statement = $this->prepareStatement(
            'INSERT INTO maa_event_logging_authoritative_audit_log '
            . '(event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $this->expectException(PDOException::class);
        $statement->execute([
            'event-corrupt',
            'system',
            1,
            'test_action',
            'target',
            2,
            'invalid-json',
            'corr-1',
            '2024-01-01 10:00:00.000000',
        ]);
    }

    /** @throws JsonException */
    public function testPrimitiveCursorPaginationUsesTimeAndId(): void
    {
        $first = new AuthoritativeAuditOutboxWriteDTO(
            'evt-1',
            'admin',
            1,
            'action',
            'target',
            1,
            'LOW',
            [],
            'corr-1',
            new DateTimeImmutable('2024-01-01 10:00:00.000000', new DateTimeZone('UTC'))
        );
        $second = new AuthoritativeAuditOutboxWriteDTO(
            'evt-2',
            'admin',
            1,
            'action',
            'target',
            1,
            'LOW',
            [],
            'corr-2',
            new DateTimeImmutable('2024-01-01 11:00:00.000000', new DateTimeZone('UTC'))
        );
        $third = new AuthoritativeAuditOutboxWriteDTO(
            'evt-3',
            'admin',
            1,
            'action',
            'target',
            1,
            'LOW',
            [],
            'corr-3',
            new DateTimeImmutable('2024-01-01 11:00:00.000000', new DateTimeZone('UTC'))
        );

        $this->materializeLog($first);
        $this->materializeLog($second);
        $this->materializeLog($third);

        $pageOne = $this->query->find(new AuthoritativeAuditQueryDTO(limit: 1));
        self::assertCount(1, $pageOne);
        self::assertSame('evt-3', $pageOne[0]->eventId);

        $pageTwo = $this->query->find(new AuthoritativeAuditQueryDTO(
            cursorOccurredAt: $pageOne[0]->occurredAt,
            cursorId: $pageOne[0]->id,
            limit: 1
        ));
        self::assertCount(1, $pageTwo);
        self::assertSame('evt-2', $pageTwo[0]->eventId);

        $pageThree = $this->query->find(new AuthoritativeAuditQueryDTO(
            cursorOccurredAt: $pageTwo[0]->occurredAt,
            cursorId: $pageTwo[0]->id,
            limit: 1
        ));
        self::assertCount(1, $pageThree);
        self::assertSame('evt-1', $pageThree[0]->eventId);
    }

    public function testPrimitiveReadPreservesCallerOwnedTransaction(): void
    {
        self::assertFalse($this->pdo->inTransaction());

        $this->pdo->beginTransaction();
        $this->query->find(new AuthoritativeAuditQueryDTO(limit: 1));
        self::assertTrue($this->pdo->inTransaction());

        $this->pdo->rollBack();
        self::assertFalse($this->pdo->inTransaction());
    }

    private function prepareStatement(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            self::fail('Failed to prepare AuthoritativeAudit integration SQL.');
        }

        return $statement;
    }

    /** @throws JsonException */
    private function materializeLog(AuthoritativeAuditOutboxWriteDTO $dto): void
    {
        $statement = $this->prepareStatement(
            'INSERT INTO maa_event_logging_authoritative_audit_log '
            . '(event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $dto->eventId,
            $dto->actorType,
            $dto->actorId,
            $dto->action,
            $dto->targetType,
            $dto->targetId,
            json_encode($dto->payload, JSON_THROW_ON_ERROR),
            $dto->correlationId,
            $dto->createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ]);
    }
}
