<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\BehaviorTrace;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceCursorDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BehaviorTraceQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private BehaviorTraceQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for BehaviorTrace primitive query integration tests.');
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

        $schema = (string) file_get_contents(__DIR__ . '/../../../src/BehaviorTrace/Database/schema.maa_event_logging_behavior_trace.sql');
        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_behavior_trace');
        $this->pdo->exec($schema);
        $this->repository = new BehaviorTraceQueryMysqlRepository($this->pdo);
        $this->seedRows();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('TRUNCATE TABLE maa_event_logging_behavior_trace');
        }
        parent::tearDown();
    }

    public function testFindDescendingCursorBehaviorWithNativePreparedStatements(): void
    {
        $result = $this->repository->find(new BehaviorTraceQueryDTO(
            cursorOccurredAt: new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('UTC')),
            cursorId: 4,
            limit: 10,
        ));

        $this->assertFalse((bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        $this->assertSame(['evt-3', 'evt-2', 'evt-1'], array_map(static fn ($item): string => $item->eventId, $result));
    }

    public function testReadAscendingStreamBehaviorWithNativePreparedStatements(): void
    {
        $items = iterator_to_array($this->repository->read(new BehaviorTraceCursorDTO(
            lastOccurredAt: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            lastId: 2,
        ), 10));

        $this->assertSame(['evt-3', 'evt-4', 'evt-5'], array_map(static fn ($item): string => $item->eventId, $items));
    }

    private function seedRows(): void
    {
        $this->insertRow('evt-1', 1, '2024-01-01 10:00:00.000000');
        $this->insertRow('evt-2', 2, '2024-01-01 11:00:00.000000');
        $this->insertRow('evt-3', 3, '2024-01-01 12:00:00.000000');
        $this->insertRow('evt-4', 4, '2024-01-01 13:00:00.000000');
        $this->insertRow('evt-5', 5, '2024-01-01 13:00:00.000000');
    }

    private function insertRow(string $eventId, int $actorId, string $occurredAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO maa_event_logging_behavior_trace (
                event_id, actor_type, actor_id, action, entity_type, entity_id,
                metadata, correlation_id, request_id, route_name, ip_address, user_agent, occurred_at
            ) VALUES (
                :event_id, :actor_type, :actor_id, :action, :entity_type, :entity_id,
                :metadata, :correlation_id, :request_id, :route_name, :ip_address, :user_agent, :occurred_at
            )',
        );
        $stmt->execute([
            'event_id' => $eventId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'view',
            'entity_type' => 'document',
            'entity_id' => $actorId,
            'metadata' => '{}',
            'correlation_id' => null,
            'request_id' => 'req-' . $actorId,
            'route_name' => null,
            'ip_address' => null,
            'user_agent' => null,
            'occurred_at' => $occurredAt,
        ]);
    }
}
