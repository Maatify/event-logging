<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\DeliveryOperations;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsAdminQueryMysqlRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeliveryOperationsAdminQueryMysqlRepositoryTest extends TestCase
{
    private PDO $pdo;
    private DeliveryOperationsAdminQueryMysqlRepository $repository;

    protected function setUp(): void
    {
        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (empty($dsn)) {
            throw new RuntimeException('Missing EVENT_LOGGING_TEST_MYSQL_DSN');
        }
        $user = getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '';

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->recreateTable();

        $this->repository = new DeliveryOperationsAdminQueryMysqlRepository($this->pdo);
    }

    private function recreateTable(): void
    {
        $schema = file_get_contents(__DIR__ . '/../../../src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql');
        if (!is_string($schema)) {
            throw new RuntimeException('Failed to load schema.');
        }

        $this->pdo->exec('DROP TABLE IF EXISTS maa_event_logging_delivery_operations;');
        $this->pdo->exec($schema);
    }

    private function insertLog(
        string $eventId = 'evt-1',
        string $channel = 'chan-1',
        string $operationType = 'op-1',
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        string $status = 'success',
        int $attemptNo = 0,
        ?string $scheduledAt = null,
        ?string $completedAt = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $provider = null,
        ?string $providerMessageId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $metadata = '{}',
        string $occurredAt = '2025-01-01 10:00:00.000000'
    ): void {
        $stmt = $this->pdo->prepare('INSERT INTO maa_event_logging_delivery_operations (
            event_id, channel, operation_type, actor_type, actor_id, target_type, target_id,
            status, attempt_no, scheduled_at, completed_at, correlation_id, request_id,
            provider, provider_message_id, error_code, error_message, metadata, occurred_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )');

        $stmt->execute([
            $eventId, $channel, $operationType, $actorType, $actorId, $targetType, $targetId,
            $status, $attemptNo, $scheduledAt, $completedAt, $correlationId, $requestId,
            $provider, $providerMessageId, $errorCode, $errorMessage, $metadata, $occurredAt
        ]);
    }

    // ===== Equality Filters =====

    /** @dataProvider idFilterProvider */
    public function testItFiltersById(int $id): void
    {
        $this->insertLog(eventId: 'id-1');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(id: $id));
        $this->assertSame(1, $res->filtered);
        $this->assertSame(1, $res->items[0]->id);
    }

    /** @return array<string, array{int}> */
    public static function idFilterProvider(): array
    {
        return [
            'id=1' => [1],
        ];
    }

    public function testItFiltersByEventId(): void
    {
        $this->insertLog(eventId: 'unique-eid');
        $this->insertLog(eventId: 'other-eid');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'unique-eid'));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('unique-eid', $res->items[0]->eventId);
    }

    public function testItFiltersByChannel(): void
    {
        $this->insertLog(eventId: 'ch-1', channel: 'EMAIL');
        $this->insertLog(eventId: 'ch-2', channel: 'SMS');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(channel: 'EMAIL'));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('EMAIL', $res->items[0]->channel);
    }

    public function testItFiltersByOperationType(): void
    {
        $this->insertLog(eventId: 'ot-1', operationType: 'NOTIFICATION');
        $this->insertLog(eventId: 'ot-2', operationType: 'WEBHOOK');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(operationType: 'NOTIFICATION'));
        $this->assertSame(1, $res->filtered);
    }

    public function testItFiltersByStatus(): void
    {
        $this->insertLog(eventId: 'st-1', status: 'SENT');
        $this->insertLog(eventId: 'st-2', status: 'FAILED');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(status: 'SENT'));
        $this->assertSame(1, $res->filtered);
    }

    public function testItFiltersByCorrelationId(): void
    {
        $this->insertLog(eventId: 'ci-1', correlationId: 'corr-abc');
        $this->insertLog(eventId: 'ci-2', correlationId: 'corr-def');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(correlationId: 'corr-abc'));
        $this->assertSame(1, $res->filtered);
    }

    public function testItFiltersByRequestId(): void
    {
        $this->insertLog(eventId: 'ri-1', requestId: 'req-123');
        $this->insertLog(eventId: 'ri-2', requestId: 'req-456');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(requestId: 'req-123'));
        $this->assertSame(1, $res->filtered);
    }

    public function testItFiltersByProvider(): void
    {
        $this->insertLog(eventId: 'pv-1', provider: 'sendgrid');
        $this->insertLog(eventId: 'pv-2', provider: 'twilio');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(provider: 'sendgrid'));
        $this->assertSame(1, $res->filtered);
    }

    public function testItFiltersByProviderMessageId(): void
    {
        $this->insertLog(eventId: 'pm-1', providerMessageId: 'msg-001');
        $this->insertLog(eventId: 'pm-2', providerMessageId: 'msg-002');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(providerMessageId: 'msg-001'));
        $this->assertSame(1, $res->filtered);
    }

    public function testItFiltersByErrorCode(): void
    {
        $this->insertLog(eventId: 'ec-1', errorCode: 'TIMEOUT');
        $this->insertLog(eventId: 'ec-2', errorCode: 'RATE_LIMIT');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(errorCode: 'TIMEOUT'));
        $this->assertSame(1, $res->filtered);
    }

    /** @dataProvider actorFilterProvider */
    public function testItFiltersActorIndependently(string $actorType, ?int $actorId, int $expected): void
    {
        $this->insertLog(eventId: 'a1', actorType: 'SYS', actorId: 10);
        $this->insertLog(eventId: 'a2', actorType: 'SYS', actorId: 20);
        $this->insertLog(eventId: 'a3', actorType: 'USR', actorId: 10);
        $this->insertLog(eventId: 'a4', actorType: null, actorId: null);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            actorType: $actorType !== '' ? $actorType : null,
            actorId: $actorId,
        ));
        $this->assertSame($expected, $res->filtered);
    }

    /** @return array<string, array{string, int|null, int}> */
    public static function actorFilterProvider(): array
    {
        return [
            'type only SYS' => ['SYS', null, 2],
            'id only 10' => ['', 10, 2],
            'both SYS+10' => ['SYS', 10, 1],
            'neither' => ['', null, 4],
        ];
    }

    /** @dataProvider targetFilterProvider */
    public function testItFiltersTargetIndependently(string $targetType, ?int $targetId, int $expected): void
    {
        $this->insertLog(eventId: 't1', targetType: 'DOC', targetId: 10);
        $this->insertLog(eventId: 't2', targetType: 'DOC', targetId: 20);
        $this->insertLog(eventId: 't3', targetType: 'IMG', targetId: 10);
        $this->insertLog(eventId: 't4', targetType: null, targetId: null);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            targetType: $targetType !== '' ? $targetType : null,
            targetId: $targetId,
        ));
        $this->assertSame($expected, $res->filtered);
    }

    /** @return array<string, array{string, int|null, int}> */
    public static function targetFilterProvider(): array
    {
        return [
            'type only DOC' => ['DOC', null, 2],
            'id only 10' => ['', 10, 2],
            'both DOC+10' => ['DOC', 10, 1],
            'neither' => ['', null, 4],
        ];
    }

    // ===== Attempts =====

    /** @dataProvider attemptFilterProvider */
    public function testItFiltersAttemptRanges(?int $min, ?int $max, int $expected): void
    {
        $this->insertLog(eventId: 'att-1', attemptNo: 0);
        $this->insertLog(eventId: 'att-2', attemptNo: 2);
        $this->insertLog(eventId: 'att-3', attemptNo: 5);

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            attemptNoMin: $min,
            attemptNoMax: $max
        ));
        $this->assertSame($expected, $res->filtered);
    }

    /** @return array<string, array{int|null, int|null, int}> */
    public static function attemptFilterProvider(): array
    {
        return [
            'min only 2' => [2, null, 2],
            'max only 2' => [null, 2, 2],
            'range 2-4' => [2, 4, 1],
            'exact 0 both' => [0, 0, 1],
        ];
    }

    // ===== Date Ranges =====

    public function testScheduledAfterOnly(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', scheduledAt: '2023-01-01 10:00:00');
        $this->insertLog(eventId: 'd2', scheduledAt: '2023-01-01 12:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            scheduledAfter: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('d2', $res->items[0]->eventId);
    }

    public function testScheduledBeforeOnly(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', scheduledAt: '2023-01-01 10:00:00');
        $this->insertLog(eventId: 'd2', scheduledAt: '2023-01-01 12:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            scheduledBefore: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('d1', $res->items[0]->eventId);
    }

    public function testScheduledAfterAndBefore(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', scheduledAt: '2023-01-01 10:00:00');
        $this->insertLog(eventId: 'd2', scheduledAt: '2023-01-01 11:00:00');
        $this->insertLog(eventId: 'd3', scheduledAt: '2023-01-01 12:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            scheduledAfter: new DateTimeImmutable('2023-01-01 10:00:00', $utc),
            scheduledBefore: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(2, $res->filtered);
    }

    public function testScheduledInclusiveBoundary(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', scheduledAt: '2023-01-01 10:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            scheduledAfter: new DateTimeImmutable('2023-01-01 10:00:00', $utc),
            scheduledBefore: new DateTimeImmutable('2023-01-01 10:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
    }

    public function testCompletedAfterOnly(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', completedAt: '2023-01-01 10:00:00');
        $this->insertLog(eventId: 'd2', completedAt: '2023-01-01 12:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            completedAfter: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
    }

    public function testCompletedBeforeOnly(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', completedAt: '2023-01-01 10:00:00');
        $this->insertLog(eventId: 'd2', completedAt: '2023-01-01 12:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            completedBefore: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
    }

    public function testCompletedAfterAndBefore(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', completedAt: '2023-01-01 10:00:00');
        $this->insertLog(eventId: 'd2', completedAt: '2023-01-01 11:00:00');
        $this->insertLog(eventId: 'd3', completedAt: '2023-01-01 12:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            completedAfter: new DateTimeImmutable('2023-01-01 10:00:00', $utc),
            completedBefore: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(2, $res->filtered);
    }

    public function testCompletedInclusiveBoundary(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'd1', completedAt: '2023-01-01 10:00:00');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            completedAfter: new DateTimeImmutable('2023-01-01 10:00:00', $utc),
            completedBefore: new DateTimeImmutable('2023-01-01 10:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
    }

    public function testOccurredAfterOnly(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'o1', occurredAt: '2023-01-01 10:00:00.000000');
        $this->insertLog(eventId: 'o2', occurredAt: '2023-01-01 12:00:00.000000');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('o2', $res->items[0]->eventId);
    }

    public function testOccurredBeforeOnly(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'o1', occurredAt: '2023-01-01 10:00:00.000000');
        $this->insertLog(eventId: 'o2', occurredAt: '2023-01-01 12:00:00.000000');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            before: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('o1', $res->items[0]->eventId);
    }

    public function testOccurredAfterAndBefore(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'o1', occurredAt: '2023-01-01 10:00:00.000000');
        $this->insertLog(eventId: 'o2', occurredAt: '2023-01-01 11:00:00.000000');
        $this->insertLog(eventId: 'o3', occurredAt: '2023-01-01 12:00:00.000000');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2023-01-01 10:00:00', $utc),
            before: new DateTimeImmutable('2023-01-01 11:00:00', $utc)
        ));
        $this->assertSame(2, $res->filtered);
    }

    public function testOccurredInclusiveBoundary(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'o1', occurredAt: '2023-01-01 10:00:00.000000');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2023-01-01 10:00:00', $utc),
            before: new DateTimeImmutable('2023-01-01 10:00:00', $utc)
        ));
        $this->assertSame(1, $res->filtered);
    }

    public function testMicrosecondsNotLost(): void
    {
        $this->insertLog(eventId: 'mu-1', occurredAt: '2023-01-01 10:00:00.123456');
        $this->insertLog(eventId: 'mu-2', occurredAt: '2023-01-01 10:00:00.654321');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2023-01-01 10:00:00.123456', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2023-01-01 10:00:00.654321', new DateTimeZone('UTC'))
        ));
        $this->assertSame(2, $res->filtered);
    }

    public function testScheduledAtMicrosecondPrecision(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'sa-mu-1', scheduledAt: '2023-01-01 10:00:00.111111');
        $this->insertLog(eventId: 'sa-mu-2', scheduledAt: '2023-01-01 10:00:00.999999');

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            scheduledAfter: new DateTimeImmutable('2023-01-01 10:00:00.555555', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('sa-mu-2', $res->items[0]->eventId);
    }

    public function testCompletedAtMicrosecondPrecision(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->insertLog(eventId: 'ca-mu-1', completedAt: '2023-01-01 10:00:00.111111');
        $this->insertLog(eventId: 'ca-mu-2', completedAt: '2023-01-01 10:00:00.999999');

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            completedAfter: new DateTimeImmutable('2023-01-01 10:00:00.555555', $utc)
        ));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('ca-mu-2', $res->items[0]->eventId);
    }

    // ===== Null-State Filters =====

    public function testNullStateIsTrueReturnsNullRows(): void
    {
        $this->insertLog(
            eventId: 'ns-1',
            actorType: 'SYS',
            actorId: 10,
            targetType: 'DOC',
            targetId: 20,
            scheduledAt: '2023-06-01 10:00:00',
            completedAt: '2023-06-01 11:00:00',
            correlationId: 'corr-1',
            requestId: 'req-1',
            provider: 'prov-1',
            providerMessageId: 'msg-1',
            errorCode: 'ERR-1',
            errorMessage: 'err-msg-1',
        );
        $this->insertLog(eventId: 'ns-2');

        $fields = [
            'actorType', 'actorId', 'targetType', 'targetId',
            'scheduledAt', 'completedAt', 'correlationId', 'requestId',
            'provider', 'providerMessageId', 'errorCode', 'errorMessage',
        ];

        foreach ($fields as $field) {
            $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
                nullStateFilters: [$field => true]
            ));
            $this->assertSame(1, $res->filtered, "Failed for field: {$field} with nullState=true");
            $this->assertSame('ns-2', $res->items[0]->eventId, "Failed for field: {$field} with nullState=true");
        }
    }

    public function testNullStateIsFalseReturnsNonNullRows(): void
    {
        $this->insertLog(
            eventId: 'ns-1',
            actorType: 'SYS',
            actorId: 10,
            targetType: 'DOC',
            targetId: 20,
            scheduledAt: '2023-06-01 10:00:00',
            completedAt: '2023-06-01 11:00:00',
            correlationId: 'corr-1',
            requestId: 'req-1',
            provider: 'prov-1',
            providerMessageId: 'msg-1',
            errorCode: 'ERR-1',
            errorMessage: 'err-msg-1',
        );
        $this->insertLog(eventId: 'ns-2');

        $fields = [
            'actorType', 'actorId', 'targetType', 'targetId',
            'scheduledAt', 'completedAt', 'correlationId', 'requestId',
            'provider', 'providerMessageId', 'errorCode', 'errorMessage',
        ];

        foreach ($fields as $field) {
            $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
                nullStateFilters: [$field => false]
            ));
            $this->assertSame(1, $res->filtered, "Failed for field: {$field} with nullState=false");
            $this->assertSame('ns-1', $res->items[0]->eventId, "Failed for field: {$field} with nullState=false");
        }
    }

    public function testNullStateConflictProducesZeroResults(): void
    {
        $this->insertLog(eventId: 'ns-conflict', errorCode: 'ERR');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
            errorCode: 'ERR',
            nullStateFilters: ['errorCode' => true]
        ));
        $this->assertSame(0, $res->filtered);
    }

    // ===== LIKE Escaping =====

    /** @dataProvider likeEscapeProvider */
    public function testLikeEscape(string $pattern, string $message, int $expected): void
    {
        $this->insertLog(eventId: 'like-1', errorMessage: $message);
        $this->insertLog(eventId: 'like-2', errorMessage: 'nothing');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(errorMessageLike: $pattern));
        $this->assertSame($expected, $res->filtered);
    }

    /** @return array<string, array{string, string, int}> */
    public static function likeEscapeProvider(): array
    {
        return [
            'backslash' => ['a\\b', 'a\\b%', 1],
            'percent' => ['a%b', 'a%b', 1],
            'underscore' => ['a_b', 'a_b', 1],
        ];
    }

    // ===== Metadata =====

    public function testMetadataFilterString(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"key": "hello"}');
        $this->insertLog(eventId: 'm2', metadata: '{"key": "world"}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.key' => 'hello'
        ]));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('m1', $res->items[0]->eventId);
    }

    public function testMetadataFilterInt(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"count": 42}');
        $this->insertLog(eventId: 'm2', metadata: '{"count": 99}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.count' => 42
        ]));
        $this->assertSame(1, $res->filtered);
    }

    public function testMetadataFilterFloat(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"score": 3.14}');
        $this->insertLog(eventId: 'm2', metadata: '{"score": 2.71}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.score' => 3.14
        ]));
        $this->assertSame(1, $res->filtered);
    }

    public function testMetadataFilterTrue(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"active": true}');
        $this->insertLog(eventId: 'm2', metadata: '{"active": false}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.active' => true
        ]));
        $this->assertSame(1, $res->filtered);
    }

    public function testMetadataFilterFalse(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"active": true}');
        $this->insertLog(eventId: 'm2', metadata: '{"active": false}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.active' => false
        ]));
        $this->assertSame(1, $res->filtered);
    }

    public function testMetadataFilterJsonNull(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"val": null}');
        $this->insertLog(eventId: 'm2', metadata: '{"val": "something"}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.val' => null
        ]));
        $this->assertSame(1, $res->filtered);
    }

    public function testMetadataFilterMissingPath(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"a": 1}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.nonexistent' => 'x'
        ]));
        $this->assertSame(0, $res->filtered);
    }

    public function testMetadataFilterMultipleInOneRequest(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"a": "hello", "b": 123}');
        $this->insertLog(eventId: 'm2', metadata: '{"a": "hello", "b": 456}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.a' => 'hello',
            '$.b' => 123
        ]));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('m1', $res->items[0]->eventId);
    }

    public function testJsonNullDoesNotMatchMissingPath(): void
    {
        $this->insertLog(eventId: 'm1', metadata: '{"a": null}');
        $this->insertLog(eventId: 'm2', metadata: '{}');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.a' => null
        ]));
        $this->assertSame(1, $res->filtered);
        $this->assertSame('m1', $res->items[0]->eventId);
    }

    // ===== Pagination =====

    public function testDefaultPerPageIsTwenty(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->insertLog(eventId: "pg-{$i}");
        }
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
        $this->assertSame(20, $res->perPage);
    }

    public function testMinClampingToOne(): void
    {
        $this->insertLog(eventId: 'pg-1');
        $this->insertLog(eventId: 'pg-2');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(perPage: 0));
        $this->assertSame(1, $res->perPage);
        $this->assertCount(1, $res->items);
    }

    public function testMaxClampingToTwoHundred(): void
    {
        $this->insertLog(eventId: 'pg-1');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(perPage: 500));
        $this->assertSame(200, $res->perPage);
    }

    public function testPageOneReturnsFirstItems(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog(eventId: "pg-{$i}");
        }
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 2));
        $this->assertCount(2, $res->items);
        $this->assertSame(1, $res->page);
    }

    public function testSubsequentPage(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog(eventId: "pg-{$i}");
        }
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 2, perPage: 2));
        $this->assertCount(2, $res->items);
        $this->assertSame(2, $res->page);
    }

    public function testOverflowPage(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->insertLog(eventId: "pg-{$i}");
        }
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 5, perPage: 10));
        $this->assertSame(1, $res->page);
        $this->assertCount(3, $res->items);
        $this->assertFalse($res->hasNext);
        $this->assertFalse($res->hasPrevious);
    }

    public function testTotalCountsAllRows(): void
    {
        $this->insertLog(eventId: 'total-1', channel: 'EMAIL');
        $this->insertLog(eventId: 'total-2', channel: 'SMS');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(channel: 'EMAIL'));
        $this->assertSame(2, $res->total);
        $this->assertSame(1, $res->filtered);
    }

    public function testFilteredMatchesDataQuery(): void
    {
        $this->insertLog(eventId: 'f1', status: 'SENT');
        $this->insertLog(eventId: 'f2', status: 'FAILED');
        $this->insertLog(eventId: 'f3', status: 'SENT');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(status: 'SENT'));
        $this->assertSame(2, $res->filtered);
        $this->assertCount(2, $res->items);
    }

    public function testTotalPagesCalculation(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog(eventId: "tp-{$i}");
        }
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 2));
        $this->assertSame(3, $res->totalPages);
    }

    public function testHasNextAndPrevious(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog(eventId: "hn-{$i}");
        }
        $res1 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 2));
        $this->assertTrue($res1->hasNext);
        $this->assertFalse($res1->hasPrevious);

        $res2 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 2, perPage: 2));
        $this->assertTrue($res2->hasNext);
        $this->assertTrue($res2->hasPrevious);

        $res3 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 3, perPage: 2));
        $this->assertFalse($res3->hasNext);
        $this->assertTrue($res3->hasPrevious);
    }

    public function testDeterministicOrderingByIdTieBreaker(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertLog(eventId: "tie-{$i}", occurredAt: '2023-01-01 10:00:00.000000');
        }
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(page: 1, perPage: 5));
        $this->assertSame('tie-5', $res->items[0]->eventId);
        $this->assertSame('tie-4', $res->items[1]->eventId);
        $this->assertSame('tie-1', $res->items[4]->eventId);
    }

    public function testTotalGreaterThanFiltered(): void
    {
        $this->insertLog(eventId: 'gt1', channel: 'EMAIL');
        $this->insertLog(eventId: 'gt2', channel: 'SMS');
        $this->insertLog(eventId: 'gt3', channel: 'EMAIL');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(channel: 'EMAIL'));
        $this->assertSame(3, $res->total);
        $this->assertSame(2, $res->filtered);
    }

    // ===== Caller-Owned Transactions =====

    public function testTransactionPreservedOnSuccess(): void
    {
        $this->pdo->beginTransaction();
        $this->insertLog(eventId: 'tx-ok');
        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'tx-ok'));
        $this->assertSame(1, $res->filtered);
        $this->pdo->rollBack();

        $res2 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'tx-ok'));
        $this->assertSame(0, $res2->filtered);
    }

    public function testTransactionPreservedOnSqlFailure(): void
    {
        $this->pdo->exec('CREATE TEMPORARY TABLE maa_event_logging_delivery_operations (broken_col INT NOT NULL)');

        $this->pdo->beginTransaction();

        try {
            $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'test'));
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
            $this->assertTrue($this->pdo->inTransaction());
        } finally {
            $this->pdo->rollBack();
            $this->pdo->exec('DROP TEMPORARY TABLE IF EXISTS maa_event_logging_delivery_operations');
        }
    }

    public function testTransactionPreservedOnMappingFailure(): void
    {
        $this->pdo->exec('CREATE TEMPORARY TABLE maa_event_logging_delivery_operations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(36) NOT NULL,
            channel VARCHAR(32) NOT NULL,
            operation_type VARCHAR(64) NOT NULL,
            actor_type VARCHAR(32) NULL,
            actor_id BIGINT UNSIGNED NULL,
            target_type VARCHAR(64) NULL,
            target_id BIGINT UNSIGNED NULL,
            status VARCHAR(32) NOT NULL,
            attempt_no INT UNSIGNED NOT NULL DEFAULT 0,
            scheduled_at VARCHAR(64) NULL,
            completed_at VARCHAR(64) NULL,
            correlation_id VARCHAR(36) NULL,
            request_id VARCHAR(36) NULL,
            provider VARCHAR(32) NULL,
            provider_message_id VARCHAR(128) NULL,
            error_code VARCHAR(32) NULL,
            error_message TEXT NULL,
            metadata JSON NULL,
            occurred_at VARCHAR(64) NOT NULL
        )');
        $this->pdo->exec("INSERT INTO maa_event_logging_delivery_operations (event_id, channel, operation_type, status, metadata, occurred_at) VALUES ('x', 'c', 'o', 's', '{}', 'not-a-date')");

        $this->pdo->beginTransaction();

        try {
            $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to map DeliveryOperations row:', $e->getMessage());
            $this->assertInstanceOf(\Exception::class, $e->getPrevious());
            $this->assertTrue($this->pdo->inTransaction());
        } finally {
            $this->pdo->rollBack();
            $this->pdo->exec('DROP TEMPORARY TABLE IF EXISTS maa_event_logging_delivery_operations');
        }
    }

    // ===== Real PDOException =====

    public function testRealPdoExceptionTranslation(): void
    {
        $this->pdo->exec('DROP TABLE maa_event_logging_delivery_operations');

        try {
            $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
            $this->fail('Expected DeliveryOperationsStorageException');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to query DeliveryOperations records:', $e->getMessage());
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
        }
    }

    // ===== Repository Does Not Manage Transactions =====

    public function testRepositoryDoesNotCommitOrRollbackCallerTransaction(): void
    {
        $this->pdo->beginTransaction();

        $this->insertLog(eventId: 'tx-caller');

        $res = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'tx-caller'));
        $this->assertSame(1, $res->filtered);
        $this->assertTrue($this->pdo->inTransaction());

        $this->pdo->rollBack();

        $res2 = $this->repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(eventId: 'tx-caller'));
        $this->assertSame(0, $res2->filtered);
    }
}
