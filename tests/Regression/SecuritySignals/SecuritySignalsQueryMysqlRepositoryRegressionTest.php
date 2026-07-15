<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\SecuritySignals;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsLoggerInterface;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsQueryInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsQueryMysqlRepository;
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsDefaultPolicy;
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder;
use Maatify\EventLogging\Tests\Support\FakePdo;
use Maatify\EventLogging\Tests\Support\FixedClock;
use Maatify\EventLogging\Tests\Support\SpyLogger;
use Maatify\EventLogging\Tests\Support\ThrowingPdo;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

final class SecuritySignalsQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testPrimitiveInterfaceDtoViewAndRepositoryConstructorRemainUnchanged(): void
    {
        $find = new ReflectionMethod(SecuritySignalsQueryInterface::class, 'find');
        $this->assertSame('find', $find->getName());
        $this->assertSame('array', (string) $find->getReturnType());
        $this->assertSame(SecuritySignalsQueryDTO::class, (string) $find->getParameters()[0]->getType());

        $constructor = new ReflectionMethod(SecuritySignalsQueryDTO::class, '__construct');
        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'requestId',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));

        $query = new SecuritySignalsQueryDTO();
        $this->assertNull($query->after);
        $this->assertNull($query->before);
        $this->assertNull($query->actorType);
        $this->assertNull($query->actorId);
        $this->assertNull($query->cursorOccurredAt);
        $this->assertNull($query->cursorId);
        $this->assertSame(50, $query->limit);

        $viewConstructor = new ReflectionMethod(SecuritySignalsViewDTO::class, '__construct');
        $this->assertSame([
            'id',
            'eventId',
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'correlationId',
            'requestId',
            'routeName',
            'ipAddress',
            'userAgent',
            'metadata',
            'occurredAt',
        ], array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $viewConstructor->getParameters()));

        $repositoryConstructor = new ReflectionMethod(SecuritySignalsQueryMysqlRepository::class, '__construct');
        $this->assertCount(1, $repositoryConstructor->getParameters());
        $this->assertSame('pdo', $repositoryConstructor->getParameters()[0]->getName());
        $this->assertSame('PDO', (string) $repositoryConstructor->getParameters()[0]->getType());
    }

    public function testPrimitiveDtoAndViewSerializationKeyOrderRemainUnchanged(): void
    {
        $query = new SecuritySignalsQueryDTO(
            after: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            before: new DateTimeImmutable('2024-01-02T00:00:00+00:00'),
            actorType: 'user',
            actorId: 1,
            signalType: 'login',
            severity: 'HIGH',
            requestId: 'req',
            correlationId: 'corr',
            cursorOccurredAt: new DateTimeImmutable('2024-01-03T00:00:00+00:00'),
            cursorId: 9,
            limit: 25,
        );
        $this->assertSame([
            'after',
            'before',
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'requestId',
            'correlationId',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_keys($query->jsonSerialize()));

        $view = new SecuritySignalsViewDTO(1, 'evt', 'user', 2, 'login', 'HIGH', 'corr', 'req', 'route', '127.0.0.1', 'agent', ['ok' => true], new DateTimeImmutable('2024-01-01T00:00:00+00:00'));
        $this->assertSame([
            'id',
            'eventId',
            'actorType',
            'actorId',
            'signalType',
            'severity',
            'correlationId',
            'requestId',
            'routeName',
            'ipAddress',
            'userAgent',
            'metadata',
            'occurredAt',
        ], array_keys($view->jsonSerialize()));
    }

    public function testEveryPrimitiveFilterIndependentlyAndOrderingLimitRemainUnchanged(): void
    {
        $this->assertFilterSql(new SecuritySignalsQueryDTO(actorType: 'user'), 'actor_type = :actor_type', ['actor_type' => 'user']);
        $this->assertFilterSql(new SecuritySignalsQueryDTO(actorId: 123), 'actor_id = :actor_id', ['actor_id' => 123]);
        $this->assertFilterSql(new SecuritySignalsQueryDTO(signalType: 'login_failed'), 'signal_type = :signal_type', ['signal_type' => 'login_failed']);
        $this->assertFilterSql(new SecuritySignalsQueryDTO(severity: 'HIGH'), 'severity = :severity', ['severity' => 'HIGH']);
        $this->assertFilterSql(new SecuritySignalsQueryDTO(requestId: 'req'), 'request_id = :request_id', ['request_id' => 'req']);
        $this->assertFilterSql(new SecuritySignalsQueryDTO(correlationId: 'corr'), 'correlation_id = :correlation_id', ['correlation_id' => 'corr']);
        $this->assertFilterSql(new SecuritySignalsQueryDTO(after: new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'))), 'occurred_at >= :after', ['after' => '2024-01-01 12:00:00.123456']);
        $this->assertFilterSql(new SecuritySignalsQueryDTO(before: new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'))), 'occurred_at <= :before', ['before' => '2024-01-01 12:00:00.123456']);

        $pdo = new FakePdo();
        (new SecuritySignalsQueryMysqlRepository($pdo))->find(new SecuritySignalsQueryDTO(actorId: 1, limit: 0));
        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE actor_id = :actor_id', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('ORDER BY occurred_at DESC, id DESC', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('LIMIT 1', $pdo->lastStatement->queryString);
    }

    public function testCursorActivationAndDistinctTimestampPlaceholders(): void
    {
        $cursorAt = new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('UTC'));

        $onlyTime = new FakePdo();
        (new SecuritySignalsQueryMysqlRepository($onlyTime))->find(new SecuritySignalsQueryDTO(cursorOccurredAt: $cursorAt));
        $this->assertNotNull($onlyTime->lastStatement);
        $this->assertStringNotContainsString('cursor_at', $onlyTime->lastStatement->queryString);

        $onlyId = new FakePdo();
        (new SecuritySignalsQueryMysqlRepository($onlyId))->find(new SecuritySignalsQueryDTO(cursorId: 99));
        $this->assertNotNull($onlyId->lastStatement);
        $this->assertStringNotContainsString('cursor_at', $onlyId->lastStatement->queryString);

        $both = new FakePdo();
        (new SecuritySignalsQueryMysqlRepository($both))->find(new SecuritySignalsQueryDTO(cursorOccurredAt: $cursorAt, cursorId: 10, limit: 25));
        $this->assertNotNull($both->lastStatement);
        $this->assertStringContainsString('(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))', $both->lastStatement->queryString);
        $this->assertStringNotContainsString(':cursor_at OR', $both->lastStatement->queryString);
        $this->assertSame('2024-01-01 12:00:00.123456', $both->lastStatement->executedParams['cursor_at_before']);
        $this->assertSame('2024-01-01 12:00:00.123456', $both->lastStatement->executedParams['cursor_at_equal']);
    }

    public function testRowIterationSkipsNonArrayRowsAndPreservesHydrationFallbacks(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn([
            'not-an-array',
            [
                'id' => 'bad',
                'event_id' => [],
                'actor_type' => [],
                'actor_id' => 'bad',
                'signal_type' => [],
                'severity' => [],
                'correlation_id' => [],
                'request_id' => [],
                'route_name' => [],
                'ip_address' => [],
                'user_agent' => [],
                'metadata' => '{"ok":true}',
                'occurred_at' => [],
            ],
        ]);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($statement);

        $results = (new SecuritySignalsQueryMysqlRepository($pdo))->find(new SecuritySignalsQueryDTO());

        $this->assertCount(1, $results);
        $this->assertSame(0, $results[0]->id);
        $this->assertSame('', $results[0]->eventId);
        $this->assertNull($results[0]->actorType);
        $this->assertNull($results[0]->actorId);
        $this->assertSame('', $results[0]->signalType);
        $this->assertSame('', $results[0]->severity);
        $this->assertNull($results[0]->correlationId);
        $this->assertNull($results[0]->requestId);
        $this->assertNull($results[0]->routeName);
        $this->assertNull($results[0]->ipAddress);
        $this->assertNull($results[0]->userAgent);
        $this->assertSame(['ok' => true], $results[0]->metadata);
        $this->assertSame('1970-01-01 00:00:00.000000', $results[0]->occurredAt->format('Y-m-d H:i:s.u'));
    }

    public function testMetadataFallbacksAndMappingPrefixRemainUnchanged(): void
    {
        foreach ([null, '', '{bad', '"scalar"', '["list"]'] as $metadata) {
            $this->assertNull($this->findOneWithMetadata($metadata)->metadata);
        }

        try {
            $this->findOneWithRow(['occurred_at' => 'not a date']);
            $this->fail('Expected storage exception.');
        } catch (SecuritySignalsStorageException $exception) {
            $this->assertStringStartsWith('Failed to map SecuritySignals row: ', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    public function testQueryExceptionPrefixAndPreviousRemainUnchanged(): void
    {
        try {
            (new SecuritySignalsQueryMysqlRepository(new ThrowingPdo()))->find(new SecuritySignalsQueryDTO());
            $this->fail('Expected storage exception.');
        } catch (SecuritySignalsStorageException $exception) {
            $this->assertStringStartsWith('Failed to query SecuritySignals records: ', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    public function testRepositoryDoesNotOwnTransactions(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn([]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->never())->method('rollBack');
        $pdo->method('prepare')->willReturn($statement);

        (new SecuritySignalsQueryMysqlRepository($pdo))->find(new SecuritySignalsQueryDTO());
    }

    public function testWriteSidePolicyAndRecorderFailOpenBehaviorRemainUnchanged(): void
    {
        $policy = new SecuritySignalsDefaultPolicy();
        $this->assertSame('ANONYMOUS', $policy->normalizeActorType('bad'));
        $this->assertSame('INFO', $policy->normalizeSeverity('bad'));

        $logger = $this->createMock(SecuritySignalsLoggerInterface::class);
        $logger->method('write')->willThrowException(new RuntimeException('storage failed'));
        $fallback = new SpyLogger();

        $recorder = new SecuritySignalsRecorder($logger, new FixedClock(), $fallback);
        $recorder->record('login_failed', SecuritySignalSeverityEnum::CRITICAL, SecuritySignalActorTypeEnum::USER, 10);

        $this->assertCount(1, $fallback->logs);
        $this->assertSame('error', $fallback->logs[0]['level']);
        $this->assertSame('SecuritySignals logging failed', $fallback->logs[0]['message']);
    }

    public function testSupersededWrapperArtifactsAreAbsentAndNoRuntimeReferencesRemain(): void
    {
        $this->assertFalse(class_exists('Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryCursorDTO'));
        $this->assertFalse(class_exists('Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryPageDTO'));
        $this->assertFalse(interface_exists('Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsPaginatedQueryInterface'));
        $this->assertFalse(class_exists('Maatify\EventLogging\SecuritySignals\Service\SecuritySignalsPaginatedQueryService'));

        foreach ($this->srcFiles() as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString('SecuritySignalsPaginatedQueryInterface', $content, $file);
            $this->assertStringNotContainsString('SecuritySignalsQueryCursorDTO', $content, $file);
            $this->assertStringNotContainsString('SecuritySignalsQueryPageDTO', $content, $file);
            $this->assertStringNotContainsString('SecuritySignalsPaginatedQueryService', $content, $file);
        }
    }

    /** @param array<string, string|int> $params */
    private function assertFilterSql(SecuritySignalsQueryDTO $query, string $condition, array $params): void
    {
        $pdo = new FakePdo();
        (new SecuritySignalsQueryMysqlRepository($pdo))->find($query);

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('WHERE ' . $condition, $pdo->lastStatement->queryString);
        foreach ($params as $key => $value) {
            $this->assertSame($value, $pdo->lastStatement->executedParams[$key]);
        }
    }

    private function findOneWithMetadata(mixed $metadata): SecuritySignalsViewDTO
    {
        return $this->findOneWithRow([
            'metadata' => $metadata,
            'occurred_at' => '2024-01-01',
        ]);
    }

    /** @param array<string, mixed> $row */
    private function findOneWithRow(array $row): SecuritySignalsViewDTO
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn([$row]);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($statement);

        return (new SecuritySignalsQueryMysqlRepository($pdo))->find(new SecuritySignalsQueryDTO())[0];
    }

    /** @return list<string> */
    private function srcFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../../src/SecuritySignals'));
        $files = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        /** @var list<string> $files */
        return $files;
    }
}
