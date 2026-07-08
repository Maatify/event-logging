<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuditTrail;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailRecordDTO;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;

/**
 * @covers \Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository
 * @covers \Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository
 */
final class AuditTrailRepositoryTest extends MysqlIntegrationTestCase
{
    private AuditTrailLoggerMysqlRepository $logger;
    private AuditTrailQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo !== null) {
            $this->logger = new AuditTrailLoggerMysqlRepository($this->pdo);
            $this->query = new AuditTrailQueryMysqlRepository($this->pdo);
        }
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql';
    }

    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_audit_trail',
        ];
    }

    public function testWriteAndQueryRoundtrip(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $recordDto = new AuditTrailRecordDTO(
            eventId: 'audit-event-1',
            actorType: 'user',
            actorId: 10,
            eventKey: 'document.view',
            entityType: 'document',
            entityId: 99,
            subjectType: 'account',
            subjectId: 5,
            referrerRouteName: 'doc_view',
            referrerPath: '/docs/99',
            referrerHost: 'example.com',
            correlationId: 'req-corr',
            requestId: 'req-id',
            routeName: 'api_doc_view',
            ipAddress: '127.0.0.1',
            userAgent: 'curl',
            metadata: ['version' => '1.0'],
            occurredAt: $now
        );

        $this->logger->write($recordDto);

        $queryDto = new AuditTrailQueryDTO(
            actorType: 'user',
            actorId: 10,
            eventKey: 'document.view'
        );

        $results = $this->query->find($queryDto);
        $this->assertCount(1, $results);

        $viewDto = $results[0];
        $this->assertSame('audit-event-1', $viewDto->eventId);
        $this->assertSame('user', $viewDto->actorType);
        $this->assertSame(10, $viewDto->actorId);
        $this->assertSame('document.view', $viewDto->eventKey);
        $this->assertSame('document', $viewDto->entityType);
        $this->assertSame(99, $viewDto->entityId);
        $this->assertSame('account', $viewDto->subjectType);
        $this->assertSame(5, $viewDto->subjectId);
        $this->assertSame('req-corr', $viewDto->correlationId);
        $this->assertSame(['version' => '1.0'], $viewDto->metadata);
        $this->assertEquals($now, $viewDto->occurredAt);
    }

    public function testCursorPagination(): void
    {
        $now1 = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $now2 = new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC'));

        $dto1 = new AuditTrailRecordDTO('evt-1', 'sys', 1, 'view', 'doc', 1, null, null, null, null, null, null, null, null, null, null, [], $now1);
        $dto2 = new AuditTrailRecordDTO('evt-2', 'sys', 1, 'view', 'doc', 1, null, null, null, null, null, null, null, null, null, null, [], $now2);
        $dto3 = new AuditTrailRecordDTO('evt-3', 'sys', 1, 'view', 'doc', 1, null, null, null, null, null, null, null, null, null, null, [], $now2);

        $this->logger->write($dto1);
        $this->logger->write($dto2);
        $this->logger->write($dto3);

        $query1 = new AuditTrailQueryDTO(limit: 1);
        $res1 = $this->query->find($query1);
        $this->assertCount(1, $res1);
        $this->assertSame('evt-3', $res1[0]->eventId);

        $query2 = new AuditTrailQueryDTO(
            cursorOccurredAt: $res1[0]->occurredAt,
            cursorId: $res1[0]->id,
            limit: 1
        );
        $res2 = $this->query->find($query2);
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        $query3 = new AuditTrailQueryDTO(
            cursorOccurredAt: $res2[0]->occurredAt,
            cursorId: $res2[0]->id,
            limit: 1
        );
        $res3 = $this->query->find($query3);
        $this->assertCount(1, $res3);
        $this->assertSame('evt-1', $res3[0]->eventId);
    }
}
