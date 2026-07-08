<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\DeliveryOperations;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsQueryDTO;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsLoggerMysqlRepository;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;

/**
 * @covers \Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsLoggerMysqlRepository
 * @covers \Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsQueryMysqlRepository
 */
final class DeliveryOperationsRepositoryTest extends MysqlIntegrationTestCase
{
    private DeliveryOperationsLoggerMysqlRepository $logger;
    private DeliveryOperationsQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo !== null) {
            $this->logger = new DeliveryOperationsLoggerMysqlRepository($this->pdo);
            $this->query = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        }
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql';
    }

    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_delivery_operations',
        ];
    }

    public function testWriteAndQueryRoundtrip(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $recordDto = new DeliveryOperationRecordDTO(
            eventId: 'del-event-1',
            channel: 'email',
            operationType: 'send_welcome',
            actorType: 'system',
            actorId: 0,
            targetType: 'user',
            targetId: 100,
            status: 'sent',
            attemptNo: 1,
            scheduledAt: null,
            completedAt: null,
            correlationId: 'corr-del',
            requestId: 'req-del',
            provider: 'smtp',
            providerMessageId: 'msg-123',
            errorCode: null,
            errorMessage: null,
            metadata: ['template' => 'welcome_v1'],
            occurredAt: $now
        );

        $this->logger->log($recordDto);

        $queryDto = new DeliveryOperationsQueryDTO(
            channel: 'email',
            operationType: 'send_welcome',
            status: 'sent'
        );

        $results = $this->query->find($queryDto);
        $this->assertCount(1, $results);

        $viewDto = $results[0];
        $this->assertSame('del-event-1', $viewDto->eventId);
        $this->assertSame('email', $viewDto->channel);
        $this->assertSame('send_welcome', $viewDto->operationType);
        $this->assertSame('system', $viewDto->actorType);
        $this->assertSame(0, $viewDto->actorId);
        $this->assertSame('user', $viewDto->targetType);
        $this->assertSame(100, $viewDto->targetId);
        $this->assertSame('sent', $viewDto->status);
        $this->assertSame(1, $viewDto->attemptNo);
        $this->assertSame('corr-del', $viewDto->correlationId);
        $this->assertSame('smtp', $viewDto->provider);
        $this->assertSame('msg-123', $viewDto->providerMessageId);
        $this->assertSame(['template' => 'welcome_v1'], $viewDto->metadata);
        $this->assertEquals($now, $viewDto->occurredAt);
    }

    public function testCursorPagination(): void
    {
        $now1 = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $now2 = new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC'));

        $dto1 = new DeliveryOperationRecordDTO('evt-1', 'email', 'op', null, null, null, null, 'queued', 0, null, null, null, null, null, null, null, null, [], $now1);
        $dto2 = new DeliveryOperationRecordDTO('evt-2', 'email', 'op', null, null, null, null, 'queued', 0, null, null, null, null, null, null, null, null, [], $now2);
        $dto3 = new DeliveryOperationRecordDTO('evt-3', 'email', 'op', null, null, null, null, 'queued', 0, null, null, null, null, null, null, null, null, [], $now2);

        $this->logger->log($dto1);
        $this->logger->log($dto2);
        $this->logger->log($dto3);

        $query1 = new DeliveryOperationsQueryDTO(limit: 1);
        $res1 = $this->query->find($query1);
        $this->assertCount(1, $res1);
        $this->assertSame('evt-3', $res1[0]->eventId);

        $query2 = new DeliveryOperationsQueryDTO(
            cursorOccurredAt: $res1[0]->occurredAt,
            cursorId: $res1[0]->id,
            limit: 1
        );
        $res2 = $this->query->find($query2);
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        $query3 = new DeliveryOperationsQueryDTO(
            cursorOccurredAt: $res2[0]->occurredAt,
            cursorId: $res2[0]->id,
            limit: 1
        );
        $res3 = $this->query->find($query3);
        $this->assertCount(1, $res3);
        $this->assertSame('evt-1', $res3[0]->eventId);
    }
}
