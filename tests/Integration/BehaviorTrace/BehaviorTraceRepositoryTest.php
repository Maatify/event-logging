<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\BehaviorTrace;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceContextDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceWriterMysqlRepository;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;

/**
 * @covers \Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceWriterMysqlRepository
 * @covers \Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceQueryMysqlRepository
 */
final class BehaviorTraceRepositoryTest extends MysqlIntegrationTestCase
{
    private BehaviorTraceWriterMysqlRepository $writer;
    private BehaviorTraceQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo !== null) {
            $this->writer = new BehaviorTraceWriterMysqlRepository($this->pdo);
            $this->query = new BehaviorTraceQueryMysqlRepository($this->pdo);
        }
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/BehaviorTrace/Database/schema.maa_event_logging_behavior_trace.sql';
    }

    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_behavior_trace',
        ];
    }

    public function testWriteAndQueryRoundtrip(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $actorType = new class implements BehaviorTraceActorTypeInterface {
            public function value(): string { return 'SYSTEM'; }
        };

        $context = new BehaviorTraceContextDTO(
            actorType: $actorType,
            actorId: 2,
            correlationId: 'corr-beh',
            requestId: 'req-beh',
            routeName: 'post_create',
            ipAddress: '10.0.0.1',
            userAgent: 'Chrome',
            occurredAt: $now
        );

        $recordDto = new BehaviorTraceEventDTO(
            eventId: 'beh-event-1',
            action: 'create_record',
            entityType: 'post',
            entityId: 10,
            context: $context,
            metadata: ['status' => 'published']
        );

        $this->writer->write($recordDto);

        $queryDto = new BehaviorTraceQueryDTO(
            actorType: 'SYSTEM',
            actorId: 2,
            action: 'create_record'
        );

        $results = $this->query->find($queryDto);
        $this->assertCount(1, $results);

        $viewDto = $results[0];
        $this->assertSame('beh-event-1', $viewDto->eventId);
        $this->assertSame('SYSTEM', $viewDto->context->actorType->value());
        $this->assertSame(2, $viewDto->context->actorId);
        $this->assertSame('create_record', $viewDto->action);
        $this->assertSame('post', $viewDto->entityType);
        $this->assertSame(10, $viewDto->entityId);
        $this->assertSame(['status' => 'published'], $viewDto->metadata);
        $this->assertSame('corr-beh', $viewDto->context->correlationId);
        $this->assertEquals($now, $viewDto->context->occurredAt);
    }

    public function testCursorPagination(): void
    {
        $now1 = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $now2 = new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC'));

        $actorType = new class implements BehaviorTraceActorTypeInterface {
            public function value(): string { return 'SYSTEM'; }
        };

        $c1 = new BehaviorTraceContextDTO($actorType, 1, null, null, null, null, null, $now1);
        $c2 = new BehaviorTraceContextDTO($actorType, 1, null, null, null, null, null, $now2);

        $dto1 = new BehaviorTraceEventDTO('evt-1', 'act', null, null, $c1, []);
        $dto2 = new BehaviorTraceEventDTO('evt-2', 'act', null, null, $c2, []);
        $dto3 = new BehaviorTraceEventDTO('evt-3', 'act', null, null, $c2, []);

        $this->writer->write($dto1);
        $this->writer->write($dto2);
        $this->writer->write($dto3);

        $query1 = new BehaviorTraceQueryDTO(limit: 1);
        $res1 = $this->query->find($query1);
        $this->assertCount(1, $res1);
        $this->assertSame('evt-3', $res1[0]->eventId);

        // Find ID manually for cursor test
        $stmt = $this->pdo->query("SELECT id FROM maa_event_logging_behavior_trace WHERE event_id = 'evt-3'");
        $evt3Id = (int)$stmt->fetchColumn();

        $query2 = new BehaviorTraceQueryDTO(
            cursorOccurredAt: $res1[0]->context->occurredAt,
            cursorId: $evt3Id,
            limit: 1
        );
        $res2 = $this->query->find($query2);
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        $stmt = $this->pdo->query("SELECT id FROM maa_event_logging_behavior_trace WHERE event_id = 'evt-2'");
        $evt2Id = (int)$stmt->fetchColumn();

        $query3 = new BehaviorTraceQueryDTO(
            cursorOccurredAt: $res2[0]->context->occurredAt,
            cursorId: $evt2Id,
            limit: 1
        );
        $res3 = $this->query->find($query3);
        $this->assertCount(1, $res3);
        $this->assertSame('evt-1', $res3[0]->eventId);
    }
}
