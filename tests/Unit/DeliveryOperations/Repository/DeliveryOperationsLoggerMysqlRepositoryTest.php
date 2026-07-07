<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsLoggerMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class DeliveryOperationsLoggerMysqlRepositoryTest extends TestCase
{
    public function testLogExecutesCorrectSqlWithParameters(): void
    {
        $pdo = new FakePdo();
        $repository = new DeliveryOperationsLoggerMysqlRepository($pdo);

        $dto = new DeliveryOperationRecordDTO(
            eventId: 'evt_123',
            channel: 'email',
            operationType: 'send',
            actorType: 'system',
            actorId: 1,
            targetType: 'user',
            targetId: 456,
            status: 'sent',
            attemptNo: 1,
            scheduledAt: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            completedAt: new DateTimeImmutable('2024-01-01 12:00:05', new DateTimeZone('UTC')),
            correlationId: 'corr_1',
            requestId: 'req_1',
            provider: 'smtp',
            providerMessageId: 'msg_1',
            errorCode: null,
            errorMessage: null,
            metadata: ['subject' => 'Hello'],
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $repository->log($dto);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('INSERT INTO maa_event_logging_delivery_operations', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('evt_123', $params[':event_id']);
        $this->assertEquals('email', $params[':channel']);
        $this->assertEquals('send', $params[':operation_type']);
        $this->assertEquals('system', $params[':actor_type']);
        $this->assertEquals(1, $params[':actor_id']);
        $this->assertEquals('user', $params[':target_type']);
        $this->assertEquals(456, $params[':target_id']);
        $this->assertEquals('sent', $params[':status']);
        $this->assertEquals(1, $params[':attempt_no']);
        $this->assertEquals('2024-01-01 11:00:00.000000', $params[':scheduled_at']);
        $this->assertEquals('2024-01-01 12:00:05.000000', $params[':completed_at']);
        $this->assertEquals('corr_1', $params[':correlation_id']);
        $this->assertEquals('req_1', $params[':request_id']);
        $this->assertEquals('smtp', $params[':provider']);
        $this->assertEquals('msg_1', $params[':provider_message_id']);
        $this->assertNull($params[':error_code']);
        $this->assertNull($params[':error_message']);
        $this->assertEquals('{"subject":"Hello"}', $params[':metadata']);
        $this->assertEquals('2024-01-01 12:00:00.000000', $params[':occurred_at']);
    }

    public function testLogThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new DeliveryOperationsLoggerMysqlRepository($pdo);

        $dto = new DeliveryOperationRecordDTO(
            eventId: 'evt_123',
            channel: 'email',
            operationType: 'send',
            actorType: 'system',
            actorId: 1,
            targetType: 'user',
            targetId: 456,
            status: 'failed',
            attemptNo: 1,
            scheduledAt: null,
            completedAt: null,
            correlationId: null,
            requestId: null,
            provider: null,
            providerMessageId: null,
            errorCode: null,
            errorMessage: null,
            metadata: null,
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $this->expectException(DeliveryOperationsStorageException::class);
        $this->expectExceptionMessage('Database write failed');

        $repository->log($dto);
    }
}
