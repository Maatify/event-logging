<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Infrastructure\Mysql;

use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailAdminQueryMysqlRepository;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class AuditTrailAdminQueryMysqlRepositoryTest extends TestCase
{
    public function testCanonicalPaginationConfigurationIsConstructible(): void
    {
        $config = new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'occurred_at' => 'occurred_at',
                'id' => 'id',
            ]),
            defaultSortBy: 'occurred_at',
            defaultSortDirection: SortDirectionEnum::DESC,
            tieBreakerSortBy: 'id',
            tieBreakerDirection: SortDirectionEnum::DESC,
            defaultPerPage: 20,
            minPerPage: 1,
            maxPerPage: 200,
        );

        $this->assertSame(20, $config->defaultPerPage);
        $this->assertSame(1, $config->minPerPage);
        $this->assertSame(200, $config->maxPerPage);
        $this->assertSame('`occurred_at`', $config->sortWhitelist->quotedIdentifierFor('occurred_at'));
        $this->assertSame('`id`', $config->sortWhitelist->quotedIdentifierFor('id'));
    }

    public function testPaginateAdaptsPersistenceResultAndUsesTieBreakerOrdering(): void
    {
        $pdo = $this->createSqlitePdo();
        $this->insertRow($pdo, 1, 'evt-1', '2024-01-01 10:00:00.000000');
        $this->insertRow($pdo, 2, 'evt-2', '2024-01-01 10:00:00.000000');

        $repository = new AuditTrailAdminQueryMysqlRepository($pdo);
        $result = $repository->paginate(new AuditTrailAdminQueryRequestDTO(
            page: '1',
            perPage: '1',
            sortBy: 'occurred_at',
            sortDirection: 'DESC'
        ));

        $this->assertSame(1, $result->page);
        $this->assertSame(1, $result->perPage);
        $this->assertSame(2, $result->total);
        $this->assertSame(2, $result->filtered);
        $this->assertSame(2, $result->totalPages);
        $this->assertTrue($result->hasNext);
        $this->assertFalse($result->hasPrevious);
        $this->assertSame('occurred_at', $result->sortBy);
        $this->assertSame('DESC', $result->sortDirection);
        $this->assertSame('evt-2', $result->items[0]->eventId);
    }

    public function testFiltersAndPerPageClampingAreDelegatedToPersistence(): void
    {
        $pdo = $this->createSqlitePdo();
        $this->insertRow($pdo, 1, 'evt-1', '2024-01-01 10:00:00.000000', actorType: 'user');
        $this->insertRow($pdo, 2, 'evt-2', '2024-01-01 11:00:00.000000', actorType: 'system');

        $result = (new AuditTrailAdminQueryMysqlRepository($pdo))->paginate(new AuditTrailAdminQueryRequestDTO(
            actorType: 'user',
            page: '99',
            perPage: '999',
            sortBy: 'id',
            sortDirection: 'NOPE'
        ));

        $this->assertSame(1, $result->page);
        $this->assertSame(200, $result->perPage);
        $this->assertSame(2, $result->total);
        $this->assertSame(1, $result->filtered);
        $this->assertSame('occurred_at', $result->sortBy);
        $this->assertSame('DESC', $result->sortDirection);
        $this->assertSame('evt-1', $result->items[0]->eventId);
    }

    public function testPdoExceptionIsTranslatedToAuditTrailStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $previous = new PDOException('database down');
        $pdo->method('prepare')->willThrowException($previous);

        $repository = new AuditTrailAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new AuditTrailAdminQueryRequestDTO());
            $this->fail('Expected storage exception.');
        } catch (AuditTrailStorageException $exception) {
            $this->assertSame('Failed to query audit trail: database down', $exception->getMessage());
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function testRepositoryDoesNotOwnTransactions(): void
    {
        $pdo = $this->createSqlitePdo();
        $this->insertRow($pdo, 1, 'evt-1', '2024-01-01 10:00:00.000000');

        $this->assertFalse($pdo->inTransaction());
        (new AuditTrailAdminQueryMysqlRepository($pdo))->paginate(new AuditTrailAdminQueryRequestDTO());
        $this->assertFalse($pdo->inTransaction());

        $pdo->beginTransaction();
        (new AuditTrailAdminQueryMysqlRepository($pdo))->paginate(new AuditTrailAdminQueryRequestDTO());
        $this->assertTrue($pdo->inTransaction());
        $pdo->rollBack();
    }

    private function createSqlitePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(
            'CREATE TABLE maa_event_logging_audit_trail (
                id INTEGER PRIMARY KEY,
                event_id TEXT NOT NULL,
                actor_type TEXT NOT NULL,
                actor_id INTEGER NULL,
                event_key TEXT NOT NULL,
                entity_type TEXT NOT NULL,
                entity_id INTEGER NULL,
                subject_type TEXT NULL,
                subject_id INTEGER NULL,
                referrer_route_name TEXT NULL,
                referrer_path TEXT NULL,
                referrer_host TEXT NULL,
                correlation_id TEXT NULL,
                request_id TEXT NULL,
                route_name TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                metadata TEXT NOT NULL,
                occurred_at TEXT NOT NULL
            )'
        );

        return $pdo;
    }

    private function insertRow(
        PDO $pdo,
        int $id,
        string $eventId,
        string $occurredAt,
        string $actorType = 'user'
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO maa_event_logging_audit_trail (
                id, event_id, actor_type, actor_id, event_key, entity_type, entity_id,
                subject_type, subject_id, referrer_route_name, referrer_path, referrer_host,
                correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at
            ) VALUES (
                :id, :event_id, :actor_type, :actor_id, :event_key, :entity_type, :entity_id,
                :subject_type, :subject_id, :referrer_route_name, :referrer_path, :referrer_host,
                :correlation_id, :request_id, :route_name, :ip_address, :user_agent, :metadata, :occurred_at
            )'
        );
        $stmt->execute([
            'id' => $id,
            'event_id' => $eventId,
            'actor_type' => $actorType,
            'actor_id' => 10,
            'event_key' => 'view',
            'entity_type' => 'document',
            'entity_id' => 20,
            'subject_type' => null,
            'subject_id' => null,
            'referrer_route_name' => null,
            'referrer_path' => null,
            'referrer_host' => null,
            'correlation_id' => null,
            'request_id' => null,
            'route_name' => null,
            'ip_address' => null,
            'user_agent' => null,
            'metadata' => '{}',
            'occurred_at' => $occurredAt,
        ]);
    }
}
