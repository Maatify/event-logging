<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailRecordDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class AuditTrailLoggerMysqlRepositoryTest extends TestCase
{
    public function testWriteExecutesCorrectSqlWithParameters(): void
    {
        $pdo = new FakePdo();
        $repository = new AuditTrailLoggerMysqlRepository($pdo);

        $dto = new AuditTrailRecordDTO(
            eventId: 'evt_123',
            actorType: 'user',
            actorId: 456,
            eventKey: 'page_view',
            entityType: 'page',
            entityId: 789,
            subjectType: 'session',
            subjectId: 101,
            referrerRouteName: 'login',
            referrerPath: '/login',
            referrerHost: 'example.com',
            correlationId: 'corr_1',
            requestId: 'req_1',
            routeName: 'home',
            ipAddress: '127.0.0.1',
            userAgent: 'Browser',
            metadata: ['browser' => 'chrome'],
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $repository->write($dto);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('INSERT INTO maa_event_logging_audit_trail', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('evt_123', $params['event_id']);
        $this->assertEquals('user', $params['actor_type']);
        $this->assertEquals(456, $params['actor_id']);
        $this->assertEquals('page_view', $params['event_key']);
        $this->assertEquals('page', $params['entity_type']);
        $this->assertEquals(789, $params['entity_id']);
        $this->assertEquals('session', $params['subject_type']);
        $this->assertEquals(101, $params['subject_id']);
        $this->assertEquals('login', $params['referrer_route_name']);
        $this->assertEquals('/login', $params['referrer_path']);
        $this->assertEquals('example.com', $params['referrer_host']);
        $this->assertEquals('corr_1', $params['correlation_id']);
        $this->assertEquals('req_1', $params['request_id']);
        $this->assertEquals('home', $params['route_name']);
        $this->assertEquals('127.0.0.1', $params['ip_address']);
        $this->assertEquals('Browser', $params['user_agent']);
        $this->assertEquals('{"browser":"chrome"}', $params['metadata']);
        $this->assertEquals('2024-01-01 12:00:00.000000', $params['occurred_at']);
    }

    public function testWriteThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new AuditTrailLoggerMysqlRepository($pdo);

        $dto = new AuditTrailRecordDTO(
            eventId: 'evt_123',
            actorType: 'user',
            actorId: 456,
            eventKey: 'page_view',
            entityType: 'page',
            entityId: null,
            subjectType: null,
            subjectId: null,
            referrerRouteName: null,
            referrerPath: null,
            referrerHost: null,
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            metadata: [],
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $this->expectException(AuditTrailStorageException::class);
        $this->expectExceptionMessage('Failed to persist audit trail record');

        $repository->write($dto);
    }
}
