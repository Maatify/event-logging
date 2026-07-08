<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditOutboxWriteDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditOutboxWriterMysqlRepositoryTest extends TestCase
{
    public function testWriteExecutesCorrectSqlWithParameters(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditOutboxWriterMysqlRepository($pdo);

        $dto = new AuthoritativeAuditOutboxWriteDTO(
            eventId: 'evt_123',
            actorType: 'user',
            actorId: 456,
            action: 'login',
            targetType: 'account',
            targetId: 789,
            riskLevel: 'high',
            payload: ['key' => 'value'],
            correlationId: 'req_123',
            createdAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $repository->write($dto);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('INSERT INTO maa_event_logging_authoritative_audit_outbox', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('evt_123', $params[':event_id']);
        $this->assertEquals('user', $params[':actor_type']);
        $this->assertEquals(456, $params[':actor_id']);
        $this->assertEquals('login', $params[':action']);
        $this->assertEquals('account', $params[':target_type']);
        $this->assertEquals(789, $params[':target_id']);
        $this->assertEquals('high', $params[':risk_level']);
        $this->assertEquals('{"key":"value"}', $params[':payload']);
        $this->assertEquals('req_123', $params[':correlation_id']);
        $this->assertEquals('2024-01-01 12:00:00.000000', $params[':created_at']);
    }

    public function testWriteThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new AuthoritativeAuditOutboxWriterMysqlRepository($pdo);

        $dto = new AuthoritativeAuditOutboxWriteDTO(
            eventId: 'evt_123',
            actorType: 'user',
            actorId: 456,
            action: 'login',
            targetType: 'account',
            targetId: 789,
            riskLevel: 'high',
            payload: ['key' => 'value'],
            correlationId: 'req_123',
            createdAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage('Outbox write failed');

        $repository->write($dto);
    }

    public function testWriteWithEmptyPayloadSetsEmptyJsonObject(): void
    {
        $pdo = new FakePdo();
        $repository = new AuthoritativeAuditOutboxWriterMysqlRepository($pdo);

        $dto = new AuthoritativeAuditOutboxWriteDTO(
            eventId: 'evt_123',
            actorType: 'user',
            actorId: 456,
            action: 'login',
            targetType: 'account',
            targetId: 789,
            riskLevel: 'high',
            payload: [],
            correlationId: 'req_123',
            createdAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $repository->write($dto);

        $this->assertNotNull($pdo->lastStatement);
        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('{}', $params[':payload']);
    }
}
