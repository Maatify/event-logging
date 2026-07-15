<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\SecuritySignals;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsAdminQueryMysqlRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecuritySignalsAdminQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SecuritySignalsAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            throw new RuntimeException('EVENT_LOGGING_TEST_MYSQL_DSN is required for SecuritySignals Admin Query integration tests.');
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

        $this->resetSchema();
        $this->repository = new SecuritySignalsAdminQueryMysqlRepository($this->pdo);
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

    public function testNoFilterTotalsDataDefaultSortAndNativePreparedStatements(): void
    {
        $this->seedDefaultRows();

        $result = $this->repository->paginate(new SecuritySignalsAdminQueryRequestDTO(perPage: 20));

        $this->assertFalse((bool) $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        $this->assertSame(5, $result->total);
        $this->assertSame(5, $result->filtered);
        $this->assertCount(5, $result->items);
        $this->assertSame('evt-5', $result->items[0]->eventId);
        $this->assertSame('occurred_at', $result->sortBy);
        $this->assertSame('DESC', $result->sortDirection);
        $this->assertSame(['valid' => true], $result->items[0]->metadata);
    }

    public function testEveryScalarFilterAndIndependentActorFilters(): void
    {
        $this->seedDefaultRows();

        $this->assertSame(['evt-1', 'evt-2', 'evt-5'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(actorType: 'user', sortDirection: 'ASC')));
        $this->assertSame(['evt-5', 'evt-1'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(actorId: 10)));
        $this->assertSame(['evt-5', 'evt-1'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(actorType: 'user', actorId: 10)));
        $this->assertSame(['evt-3'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(signalType: 'password_reset')));
        $this->assertSame(['evt-4'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(severity: 'CRITICAL')));
        $this->assertSame(['evt-3'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(requestId: 'req-3')));
        $this->assertSame(['evt-4'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(correlationId: '00000000-0000-0000-0000-000000000004')));
        $this->assertSame(['evt-5'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(
            actorType: 'user',
            actorId: 10,
            signalType: 'login_failed',
            severity: 'HIGH',
            requestId: 'req-5',
            correlationId: '00000000-0000-0000-0000-000000000005',
        )));
    }

    public function testDateBoundariesPaginationTieBreakerNullableColumnsAndTransactionOwnership(): void
    {
        $this->seedDefaultRows();

        $this->assertSame(['evt-3', 'evt-5', 'evt-4'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC',
        )));
        $this->assertSame(['evt-2'], $this->eventIds(new SecuritySignalsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 11:00:00', new DateTimeZone('UTC')),
            sortDirection: 'ASC',
        )));

        $zero = $this->repository->paginate(new SecuritySignalsAdminQueryRequestDTO(actorType: 'missing'));
        $this->assertSame(5, $zero->total);
        $this->assertSame(0, $zero->filtered);
        $this->assertSame(0, $zero->totalPages);

        $first = $this->repository->paginate(new SecuritySignalsAdminQueryRequestDTO(page: 1, perPage: 2, sortDirection: 'ASC'));
        $this->assertSame(['evt-1', 'evt-2'], array_map(static fn ($item): string => $item->eventId, $first->items));

        $clamped = $this->repository->paginate(new SecuritySignalsAdminQueryRequestDTO(perPage: 999, sortBy: 'id', sortDirection: 'bad'));
        $this->assertSame(200, $clamped->perPage);
        $this->assertSame('occurred_at', $clamped->sortBy);
        $this->assertSame('DESC', $clamped->sortDirection);

        $this->insertSecuritySignalsRow('evt-null', 'system', null, 'login', 'INFO', null, null, null, null, null, [], '2024-01-01 14:00:00.000000');
        $item = $this->repository->paginate(new SecuritySignalsAdminQueryRequestDTO())->items[0];
        $this->assertNull($item->actorId);
        $this->assertNull($item->requestId);
        $this->assertSame([], $item->metadata);

        $this->assertFalse($this->pdo->inTransaction());
        $this->pdo->beginTransaction();
        $this->repository->paginate(new SecuritySignalsAdminQueryRequestDTO());
        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
    }

    /** @return list<string> */
    private function eventIds(SecuritySignalsAdminQueryRequestDTO $request): array
    {
        return array_map(
            static fn ($item): string => $item->eventId,
            $this->repository->paginate($request)->items,
        );
    }

    private function seedDefaultRows(): void
    {
        $this->insertSecuritySignalsRow('evt-1', 'user', 10, 'login_failed', 'HIGH', 'req-1', '00000000-0000-0000-0000-000000000001', 'api.one', '127.0.0.1', 'agent', ['row' => 1], '2024-01-01 10:00:00.000000');
        $this->insertSecuritySignalsRow('evt-2', 'user', 20, 'login_failed', 'LOW', 'req-2', '00000000-0000-0000-0000-000000000002', null, null, null, ['row' => 2], '2024-01-01 11:00:00.000000');
        $this->insertSecuritySignalsRow('evt-3', 'system', 30, 'password_reset', 'MEDIUM', 'req-3', '00000000-0000-0000-0000-000000000003', null, null, null, ['row' => 3], '2024-01-01 12:00:00.000000');
        $this->insertSecuritySignalsRow('evt-4', 'admin', 40, 'mfa_failed', 'CRITICAL', 'req-4', '00000000-0000-0000-0000-000000000004', null, null, null, ['row' => 4], '2024-01-01 13:00:00.000000');
        $this->insertSecuritySignalsRow('evt-5', 'user', 10, 'login_failed', 'HIGH', 'req-5', '00000000-0000-0000-0000-000000000005', null, null, null, ['valid' => true], '2024-01-01 13:00:00.000000');
    }

    private function resetSchema(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../../src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql');
        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_security_signals');
        $this->pdo->exec($schema);
        $this->pdo->exec('TRUNCATE TABLE maa_event_logging_security_signals');
    }

    /** @param array<string, mixed> $metadata */
    private function insertSecuritySignalsRow(
        string $eventId,
        string $actorType,
        ?int $actorId,
        string $signalType,
        string $severity,
        ?string $requestId,
        ?string $correlationId,
        ?string $routeName,
        ?string $ipAddress,
        ?string $userAgent,
        array $metadata,
        string $occurredAt,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO maa_event_logging_security_signals (
                event_id, actor_type, actor_id, signal_type, severity,
                correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at
            ) VALUES (
                :event_id, :actor_type, :actor_id, :signal_type, :severity,
                :correlation_id, :request_id, :route_name, :ip_address, :user_agent, :metadata, :occurred_at
            )',
        );
        $stmt->execute([
            'event_id' => $eventId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'signal_type' => $signalType,
            'severity' => $severity,
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
            'route_name' => $routeName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt,
        ]);
    }
}
