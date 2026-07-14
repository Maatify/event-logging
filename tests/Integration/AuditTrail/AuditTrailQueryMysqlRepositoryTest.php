<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuditTrail;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailRecordDTO;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditTrailQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AuditTrailLoggerMysqlRepository $logger;
    private AuditTrailQueryMysqlRepository $query;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for AuditTrail primitive query integration tests.');
        }

        $this->pdo = new PDO(
            $dsn,
            getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root',
            getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->resetSchema();
        $this->logger = new AuditTrailLoggerMysqlRepository($this->pdo);
        $this->query = new AuditTrailQueryMysqlRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if (isset($this->pdo)) {
            $this->pdo->exec('TRUNCATE TABLE maa_event_logging_audit_trail');
        }
        parent::tearDown();
    }

    public function testPrimitiveWriteAndQueryRoundtripStillWorks(): void
    {
        $occurredAt = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));

        $this->logger->write(new AuditTrailRecordDTO(
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
            correlationId: '00000000-0000-0000-0000-000000000011',
            requestId: 'req-id',
            routeName: 'api_doc_view',
            ipAddress: '127.0.0.1',
            userAgent: 'curl',
            metadata: ['version' => '1.0'],
            occurredAt: $occurredAt
        ));

        $results = $this->query->find(new AuditTrailQueryDTO(
            actorType: 'user',
            actorId: 10,
            eventKey: 'document.view',
            entityType: 'document',
            entityId: 99,
            subjectType: 'account',
            subjectId: 5,
            requestId: 'req-id',
            correlationId: '00000000-0000-0000-0000-000000000011',
            after: $occurredAt,
            before: $occurredAt
        ));

        $this->assertCount(1, $results);
        $this->assertSame('audit-event-1', $results[0]->eventId);
        $this->assertSame(['version' => '1.0'], $results[0]->metadata);
        $this->assertEquals($occurredAt, $results[0]->occurredAt);
        $this->assertFalse((bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }

    public function testPrimitiveCursorOrderingAndLimitRemainUnchanged(): void
    {
        $this->logger->write($this->record('evt-1', '2024-01-01 10:00:00'));
        $this->logger->write($this->record('evt-2', '2024-01-01 11:00:00'));
        $this->logger->write($this->record('evt-3', '2024-01-01 11:00:00'));

        $first = $this->query->find(new AuditTrailQueryDTO(limit: 1));
        $this->assertSame('evt-3', $first[0]->eventId);

        $second = $this->query->find(new AuditTrailQueryDTO(
            cursorOccurredAt: $first[0]->occurredAt,
            cursorId: $first[0]->id,
            limit: 1
        ));
        $this->assertSame('evt-2', $second[0]->eventId);

        $third = $this->query->find(new AuditTrailQueryDTO(
            cursorOccurredAt: $second[0]->occurredAt,
            cursorId: $second[0]->id,
            limit: 1
        ));
        $this->assertSame('evt-1', $third[0]->eventId);
    }

    public function testPrimitiveRepositoryDoesNotOwnTransactions(): void
    {
        $this->logger->write($this->record('evt-1', '2024-01-01 10:00:00'));

        $this->assertFalse($this->pdo->inTransaction());
        $this->query->find(new AuditTrailQueryDTO());
        $this->assertFalse($this->pdo->inTransaction());

        $this->pdo->beginTransaction();
        $this->query->find(new AuditTrailQueryDTO());
        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
    }

    private function record(string $eventId, string $occurredAt): AuditTrailRecordDTO
    {
        return new AuditTrailRecordDTO(
            eventId: $eventId,
            actorType: 'sys',
            actorId: 1,
            eventKey: 'view',
            entityType: 'doc',
            entityId: 1,
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
            occurredAt: new DateTimeImmutable($occurredAt, new DateTimeZone('UTC'))
        );
    }

    private function resetSchema(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../../src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql');
        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_audit_trail');
        $this->pdo->exec($schema);
        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_audit_trail');
    }
}
