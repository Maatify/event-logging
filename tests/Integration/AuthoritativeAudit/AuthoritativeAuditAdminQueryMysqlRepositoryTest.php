<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use Maatify\EventLogging\Tests\Integration\AuthoritativeAudit\Support\StrictAuthoritativeAuditMysqlIntegrationTestCase;
use PDO;
use PDOStatement;

/**
 * @covers \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository
 */
final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends StrictAuthoritativeAuditMysqlIntegrationTestCase
{
    private AuthoritativeAuditAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = $this->requirePdo();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        self::assertFalse((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));

        $this->repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
    }

    protected function getDomainSchemaFile(): string
    {
        return 'src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql';
    }

    /** @return list<string> */
    protected function getTableNames(): array
    {
        return [
            'maa_event_logging_authoritative_audit_log',
            'maa_event_logging_authoritative_audit_outbox',
        ];
    }

    public function testNoFilterTotalsDataAndDefaultSort(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt', 1, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'sys', 2, 'act-2', 'tgt', 2, null, 'corr-2', '2024-01-01 11:00:00.000000');

        $result = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());

        self::assertSame(2, $result->total);
        self::assertSame(2, $result->filtered);
        self::assertSame(['evt-2', 'evt-1'], self::eventIds($result->items));
        self::assertSame('occurred_at', $result->sortBy);
        self::assertSame('DESC', $result->sortDirection);
    }

    public function testEveryFilterIndependentlyAndConcurrently(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act-1', 'tgt1', 11, null, 'corr-1', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'user', 2, 'act-2', 'tgt2', 22, null, 'corr-2', '2024-01-01 11:00:00.000000');
        $this->insertLog('evt-3', 'sys', 3, 'act-3', 'tgt3', 33, null, 'corr-3', '2024-01-01 12:00:00.000000');

        $requests = [
            new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-2'),
            new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'user'),
            new AuthoritativeAuditAdminQueryRequestDTO(actorId: 2),
            new AuthoritativeAuditAdminQueryRequestDTO(actorType: 'user', actorId: 2),
            new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'tgt2'),
            new AuthoritativeAuditAdminQueryRequestDTO(targetId: 22),
            new AuthoritativeAuditAdminQueryRequestDTO(targetType: 'tgt2', targetId: 22),
            new AuthoritativeAuditAdminQueryRequestDTO(action: 'act-2'),
            new AuthoritativeAuditAdminQueryRequestDTO(correlationId: 'corr-2'),
            new AuthoritativeAuditAdminQueryRequestDTO(
                eventId: 'evt-2',
                actorType: 'user',
                actorId: 2,
                targetType: 'tgt2',
                targetId: 22,
                action: 'act-2',
                correlationId: 'corr-2'
            ),
        ];

        foreach ($requests as $request) {
            $result = $this->repository->paginate($request);
            self::assertSame(3, $result->total);
            self::assertSame(1, $result->filtered);
            self::assertSame(['evt-2'], self::eventIds($result->items));
        }
    }

    public function testDateBoundsAreInclusiveAndConvertedFromCairoToUtc(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act', 'tgt', 1, null, 'corr-1', '2024-06-01 08:00:00.000000');
        $this->insertLog('evt-2', 'sys', 1, 'act', 'tgt', 1, null, 'corr-2', '2024-06-01 09:00:00.500000');
        $this->insertLog('evt-3', 'sys', 1, 'act', 'tgt', 1, null, 'corr-3', '2024-06-01 10:00:00.000000');

        $cairo = new DateTimeZone('Africa/Cairo');
        $after = new DateTimeImmutable('2024-06-01 11:00:00.000000', $cairo);
        $before = new DateTimeImmutable('2024-06-01 12:00:00.500000', $cairo);

        $afterOnly = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(after: $after));
        self::assertSame(['evt-3', 'evt-2', 'evt-1'], self::eventIds($afterOnly->items));

        $beforeOnly = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(before: $before));
        self::assertSame(['evt-2', 'evt-1'], self::eventIds($beforeOnly->items));

        $range = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(after: $after, before: $before));
        self::assertSame(['evt-2', 'evt-1'], self::eventIds($range->items));

        $equal = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(after: $after, before: $after));
        self::assertSame(['evt-1'], self::eventIds($equal->items));
    }

    public function testZeroRowsNormalizePageAndMetadata(): void
    {
        $result = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: 10, perPage: 20));

        self::assertSame(0, $result->total);
        self::assertSame(0, $result->filtered);
        self::assertSame([], $result->items);
        self::assertSame(1, $result->page);
        self::assertSame(0, $result->totalPages);
        self::assertFalse($result->hasNext);
        self::assertFalse($result->hasPrevious);
    }

    public function testPagesAndOverflowUseStableIdDescTieBreaker(): void
    {
        for ($index = 1; $index <= 5; $index++) {
            $this->insertLog("evt-{$index}", 'sys', 1, 'act', 'tgt', 1, null, 'corr', '2024-01-01 10:00:00.000000');
        }

        $pageOne = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: 1, perPage: 2));
        self::assertSame(['evt-5', 'evt-4'], self::eventIds($pageOne->items));
        self::assertSame(3, $pageOne->totalPages);
        self::assertTrue($pageOne->hasNext);
        self::assertFalse($pageOne->hasPrevious);

        $pageTwo = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: 2, perPage: 2));
        self::assertSame(['evt-3', 'evt-2'], self::eventIds($pageTwo->items));
        self::assertTrue($pageTwo->hasNext);
        self::assertTrue($pageTwo->hasPrevious);

        $overflow = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: 10, perPage: 2));
        self::assertSame(1, $overflow->page);
        self::assertSame(['evt-5', 'evt-4'], self::eventIds($overflow->items));
    }

    public function testPerPageClampingAndRequestedSortDirections(): void
    {
        $this->insertLog('evt-1', 'sys', 1, 'act', 'tgt', 1, null, 'corr', '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-2', 'sys', 1, 'act', 'tgt', 1, null, 'corr', '2024-01-01 11:00:00.000000');

        $minimum = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(perPage: 0));
        self::assertSame(1, $minimum->perPage);

        $maximum = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(perPage: 500));
        self::assertSame(200, $maximum->perPage);

        $ascending = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'occurred_at', sortDirection: 'asc'));
        self::assertSame(['evt-1', 'evt-2'], self::eventIds($ascending->items));
        self::assertSame('ASC', $ascending->sortDirection);

        $descending = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(sortBy: 'occurred_at', sortDirection: 'desc'));
        self::assertSame(['evt-2', 'evt-1'], self::eventIds($descending->items));
        self::assertSame('DESC', $descending->sortDirection);
    }

    public function testNullableColumnsAndAssociativeJsonHydration(): void
    {
        $this->insertLog('evt-null', 'sys', null, 'act', 'tgt', null, null, null, '2024-01-01 10:00:00.000000');
        $this->insertLog('evt-json', 'sys', 1, 'act', 'tgt', 1, '{"old":"a","new":"b"}', 'corr', '2024-01-01 11:00:00.000000');

        $nullable = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-null'));
        $nullableItem = $nullable->items[0];
        self::assertNull($nullableItem->actorId);
        self::assertNull($nullableItem->targetId);
        self::assertNull($nullableItem->correlationId);
        self::assertNull($nullableItem->changes);
        self::assertNull($nullableItem->ipAddress);
        self::assertNull($nullableItem->userAgent);

        $json = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-json'));
        self::assertSame(['old' => 'a', 'new' => 'b'], $json->items[0]->changes);
    }

    public function testCallerOwnedTransactionIsPreserved(): void
    {
        $pdo = $this->requirePdo();
        self::assertFalse($pdo->inTransaction());

        $pdo->beginTransaction();
        $this->insertLog('evt-txn', 'sys', 1, 'act', 'tgt', 1, null, 'corr', '2024-01-01 10:00:00.000000');

        $inside = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-txn'));
        self::assertSame(['evt-txn'], self::eventIds($inside->items));
        self::assertTrue($pdo->inTransaction());

        $pdo->rollBack();
        self::assertFalse($pdo->inTransaction());

        $afterRollback = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(eventId: 'evt-txn'));
        self::assertSame([], $afterRollback->items);
    }

    public function testAdminQueryReadsMaterializedLogOnly(): void
    {
        $statement = $this->prepareStatement(
            'INSERT INTO maa_event_logging_authoritative_audit_outbox '
            . '(event_id, actor_type, actor_id, action, target_type, target_id, risk_level, payload, correlation_id, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            'evt-outbox', 'sys', 1, 'act', 'tgt', 1, 'LOW', '{}', 'corr', '2024-01-01 10:00:00.000000',
        ]);

        $result = $this->repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());

        self::assertSame(0, $result->total);
        self::assertSame([], $result->items);
    }

    private function requirePdo(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            self::fail('Integration tests require a real MySQL PDO connection.');
        }

        return $this->pdo;
    }

    private function prepareStatement(string $sql): PDOStatement
    {
        $statement = $this->requirePdo()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            self::fail('Failed to prepare AuthoritativeAudit integration SQL.');
        }

        return $statement;
    }

    /**
     * @param list<AuthoritativeAuditViewDTO> $items
     * @return list<string>
     */
    private static function eventIds(array $items): array
    {
        return array_map(
            static fn (AuthoritativeAuditViewDTO $item): string => $item->eventId,
            $items
        );
    }

    private function insertLog(
        string $eventId,
        string $actorType,
        ?int $actorId,
        string $action,
        string $targetType,
        ?int $targetId,
        ?string $changes,
        ?string $correlationId,
        string $occurredAt
    ): void {
        $statement = $this->prepareStatement(
            'INSERT INTO maa_event_logging_authoritative_audit_log '
            . '(event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $eventId,
            $actorType,
            $actorId,
            $action,
            $targetType,
            $targetId,
            $changes,
            $correlationId,
            $occurredAt,
        ]);
    }
}
