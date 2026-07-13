<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\SecuritySignals;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsLoggerMysqlRepository;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;

/**
 * @covers \Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsLoggerMysqlRepository
 * @covers \Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsQueryMysqlRepository
 */
final class SecuritySignalsRepositoryTest extends MysqlIntegrationTestCase
{
    private SecuritySignalsLoggerMysqlRepository $logger;
    private SecuritySignalsQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdo !== null) {
            $this->logger = new SecuritySignalsLoggerMysqlRepository($this->pdo);
            $this->query = new SecuritySignalsQueryMysqlRepository($this->pdo);
        }
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql';
    }

    /**
     * @return array<int, string>
     */
    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_security_signals',
        ];
    }

    public function testWriteAndQueryRoundtrip(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $recordDto = new SecuritySignalRecordDTO(
            eventId: 'sec-event-1',
            actorType: 'anonymous',
            actorId: null,
            signalType: 'login_failed',
            severity: 'WARNING',
            correlationId: 'corr-sec',
            requestId: 'req-sec',
            routeName: 'login',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla',
            metadata: ['reason' => 'invalid_password'],
            occurredAt: $now
        );

        $this->logger->write($recordDto);

        $queryDto = new SecuritySignalsQueryDTO(
            actorType: 'anonymous',
            signalType: 'login_failed',
            severity: 'WARNING'
        );

        $results = $this->query->find($queryDto);
        $this->assertCount(1, $results);

        $viewDto = $results[0];
        $this->assertSame('sec-event-1', $viewDto->eventId);
        $this->assertSame('anonymous', $viewDto->actorType);
        $this->assertNull($viewDto->actorId);
        $this->assertSame('login_failed', $viewDto->signalType);
        $this->assertSame('WARNING', $viewDto->severity);
        $this->assertSame('corr-sec', $viewDto->correlationId);
        $this->assertSame(['reason' => 'invalid_password'], $viewDto->metadata);
        $this->assertEquals($now, $viewDto->occurredAt);
    }

    public function testCursorPagination(): void
    {
        $now1 = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $now2 = new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC'));

        $dto1 = new SecuritySignalRecordDTO('evt-1', 'anon', null, 'login', 'INFO', null, null, null, null, null, [], $now1);
        $dto2 = new SecuritySignalRecordDTO('evt-2', 'anon', null, 'login', 'INFO', null, null, null, null, null, [], $now2);
        $dto3 = new SecuritySignalRecordDTO('evt-3', 'anon', null, 'login', 'INFO', null, null, null, null, null, [], $now2);

        $this->logger->write($dto1);
        $this->logger->write($dto2);
        $this->logger->write($dto3);

        $query1 = new SecuritySignalsQueryDTO(limit: 1);
        $res1 = $this->query->find($query1);
        $this->assertCount(1, $res1);
        $this->assertSame('evt-3', $res1[0]->eventId);

        $query2 = new SecuritySignalsQueryDTO(
            cursorOccurredAt: $res1[0]->occurredAt,
            cursorId: $res1[0]->id,
            limit: 1
        );
        $res2 = $this->query->find($query2);
        $this->assertCount(1, $res2);
        $this->assertSame('evt-2', $res2[0]->eventId);

        $query3 = new SecuritySignalsQueryDTO(
            cursorOccurredAt: $res2[0]->occurredAt,
            cursorId: $res2[0]->id,
            limit: 1
        );
        $res3 = $this->query->find($query3);
        $this->assertCount(1, $res3);
        $this->assertSame('evt-1', $res3[0]->eventId);
    }
}
