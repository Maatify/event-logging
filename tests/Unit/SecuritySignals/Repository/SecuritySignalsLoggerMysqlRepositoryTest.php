<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsLoggerMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\ThrowingStatementPdo;
use PHPUnit\Framework\TestCase;

class SecuritySignalsLoggerMysqlRepositoryTest extends TestCase
{
    public function testWriteExecutesCorrectSqlWithParameters(): void
    {
        $pdo = new FakePdo();
        $repository = new SecuritySignalsLoggerMysqlRepository($pdo);

        $dto = new SecuritySignalRecordDTO(
            eventId: 'evt_123',
            actorType: 'user',
            actorId: 456,
            signalType: 'login_failed',
            severity: 'high',
            correlationId: 'corr_1',
            requestId: 'req_1',
            routeName: 'login',
            ipAddress: '127.0.0.1',
            userAgent: 'Browser',
            metadata: ['reason' => 'bad_password'],
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $repository->write($dto);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('INSERT INTO maa_event_logging_security_signals', $pdo->lastStatement->queryString);

        $params = $pdo->lastStatement->executedParams;
        $this->assertEquals('evt_123', $params['event_id']);
        $this->assertEquals('user', $params['actor_type']);
        $this->assertEquals(456, $params['actor_id']);
        $this->assertEquals('login_failed', $params['signal_type']);
        $this->assertEquals('high', $params['severity']);
        $this->assertEquals('corr_1', $params['correlation_id']);
        $this->assertEquals('req_1', $params['request_id']);
        $this->assertEquals('login', $params['route_name']);
        $this->assertEquals('127.0.0.1', $params['ip_address']);
        $this->assertEquals('Browser', $params['user_agent']);
        $this->assertEquals('{"reason":"bad_password"}', $params['metadata']);
        $this->assertEquals('2024-01-01 12:00:00.000000', $params['occurred_at']);
    }

    public function testWriteThrowsStorageExceptionOnPdoFailure(): void
    {
        $pdo = new ThrowingStatementPdo();
        $repository = new SecuritySignalsLoggerMysqlRepository($pdo);

        $dto = new SecuritySignalRecordDTO(
            eventId: 'evt_123',
            actorType: 'user',
            actorId: 456,
            signalType: 'login_failed',
            severity: 'high',
            correlationId: null,
            requestId: null,
            routeName: null,
            ipAddress: null,
            userAgent: null,
            metadata: [],
            occurredAt: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC'))
        );

        $this->expectException(SecuritySignalsStorageException::class);
        $this->expectExceptionMessage('Failed to persist security signal record');

        $repository->write($dto);
    }
}
