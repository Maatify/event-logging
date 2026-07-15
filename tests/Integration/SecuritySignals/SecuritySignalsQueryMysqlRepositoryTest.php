<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\SecuritySignals;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsLoggerMysqlRepository;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecuritySignalsQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SecuritySignalsLoggerMysqlRepository $logger;
    private SecuritySignalsQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for SecuritySignals primitive integration tests.');
        }

        $this->pdo = new PDO(
            $dsn,
            getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root',
            getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        $schema = (string) file_get_contents(__DIR__ . '/../../../src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql');
        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_security_signals');
        $this->pdo->exec($schema);
        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_security_signals');

        $this->logger = new SecuritySignalsLoggerMysqlRepository($this->pdo);
        $this->query = new SecuritySignalsQueryMysqlRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if (isset($this->pdo)) {
            $this->pdo->exec('TRUNCATE TABLE maa_event_logging_security_signals');
        }
        parent::tearDown();
    }

    public function testCursorPaginationUsesNativePreparedStatementsAndIndependentActorFilters(): void
    {
        $now1 = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $now2 = new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC'));

        $this->logger->write(new SecuritySignalRecordDTO('evt-1', 'anon', null, 'login', 'INFO', null, null, null, null, null, [], $now1));
        $this->logger->write(new SecuritySignalRecordDTO('evt-2', 'anon', 20, 'login', 'INFO', null, null, null, null, null, [], $now2));
        $this->logger->write(new SecuritySignalRecordDTO('evt-3', 'anon', 20, 'login', 'INFO', null, null, null, null, null, [], $now2));

        $this->assertFalse((bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        $this->assertSame(['evt-3', 'evt-2'], array_map(
            static fn ($item): string => $item->eventId,
            $this->query->find(new SecuritySignalsQueryDTO(actorId: 20)),
        ));

        $res1 = $this->query->find(new SecuritySignalsQueryDTO(limit: 1));
        $this->assertSame('evt-3', $res1[0]->eventId);

        $res2 = $this->query->find(new SecuritySignalsQueryDTO(
            cursorOccurredAt: $res1[0]->occurredAt,
            cursorId: $res1[0]->id,
            limit: 1,
        ));
        $this->assertSame('evt-2', $res2[0]->eventId);

        $this->pdo->beginTransaction();
        $this->query->find(new SecuritySignalsQueryDTO(limit: 1));
        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
    }
}
