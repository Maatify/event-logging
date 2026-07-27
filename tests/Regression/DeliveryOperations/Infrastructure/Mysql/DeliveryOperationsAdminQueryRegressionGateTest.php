<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\DeliveryOperations\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Maatify\EventLogging\Common\SystemClock;
use Maatify\EventLogging\DeliveryOperations\Command\RecordDeliveryOperationCommand;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsAdminQueryInterface;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsLoggerInterface;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsPolicyInterface;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsQueryInterface;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminPageResultDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsQueryDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsViewDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryChannelEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryOperationTypeEnum;
use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryStatusEnum;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsAdminQueryMysqlRepository;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsLoggerMysqlRepository;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsQueryMysqlRepository;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsRowMapper;
use Maatify\EventLogging\DeliveryOperations\Recorder\DeliveryOperationsDefaultPolicy;
use Maatify\EventLogging\DeliveryOperations\Recorder\DeliveryOperationsRecorder;
use Maatify\EventLogging\Factory\DeliveryOperationsFactory;
use Maatify\EventLogging\Bootstrap\EventLoggingBindings;
use Maatify\EventLogging\Provider\EventLoggingProvider;
use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DeliveryOperationsAdminQueryRegressionGateTest extends TestCase
{
    /** @var PDO&MockObject */
    private PDO $pdo;
    /** @var PDOStatement&MockObject */
    private PDOStatement $statement;
    /** @var PDOStatement&MockObject */
    private PDOStatement $countStatement;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->statement = $this->createMock(PDOStatement::class);
        $this->countStatement = $this->createMock(PDOStatement::class);
    }

    // --- Primitive Query Contract ---

    public function testPrimitiveFindSignatureIsExact(): void
    {
        $ref = new \ReflectionClass(DeliveryOperationsQueryInterface::class);
        $methods = $ref->getMethods();

        $this->assertCount(1, $methods);
        $this->assertSame('find', $methods[0]->getName());
        $this->assertSame(DeliveryOperationsQueryDTO::class, $methods[0]->getParameters()[0]->getType()->getName());
        $this->assertTrue($methods[0]->getReturnType()->getName() === 'array');
    }

    public function testPrimitiveQueryDtoConstructorOrderAndTypes(): void
    {
        $ref = new \ReflectionClass(DeliveryOperationsQueryDTO::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $paramNames = array_map(fn($p) => $p->getName(), $params);

        $this->assertSame([
            'after', 'before', 'actorType', 'actorId', 'targetType', 'targetId',
            'channel', 'operationType', 'status', 'requestId', 'correlationId',
            'cursorOccurredAt', 'cursorId', 'limit',
        ], $paramNames);

        $expectedTypes = [
            'after' => 'DateTimeImmutable',
            'before' => 'DateTimeImmutable',
            'actorType' => 'string',
            'actorId' => 'int',
            'targetType' => 'string',
            'targetId' => 'int',
            'channel' => 'string',
            'operationType' => 'string',
            'status' => 'string',
            'requestId' => 'string',
            'correlationId' => 'string',
            'cursorOccurredAt' => 'DateTimeImmutable',
            'cursorId' => 'int',
            'limit' => 'int',
        ];

        foreach ($params as $param) {
            $name = $param->getName();
            $this->assertArrayHasKey($name, $expectedTypes);
            $this->assertSame($expectedTypes[$name], $param->getType()->getName(), "Type mismatch for parameter: {$name}");

            if ($name === 'limit') {
                $this->assertFalse($param->getType()->allowsNull());
                $this->assertTrue($param->isDefaultValueAvailable());
                $this->assertSame(50, $param->getDefaultValue());
            } else {
                $this->assertTrue($param->getType()->allowsNull());
                $this->assertTrue($param->isDefaultValueAvailable());
                $this->assertNull($param->getDefaultValue());
            }
        }
    }

    // --- Primitive Repository Behavior ---

    public function testPrimitiveRepositoryPreservesFilterOrderAndParams(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                $actorPos = strpos($sql, 'actor_type = :actor_type');
                $targetPos = strpos($sql, 'target_type = :target_type');
                $channelPos = strpos($sql, 'channel = :channel');
                return $actorPos < $targetPos && $targetPos < $channelPos
                    && str_contains($sql, 'ORDER BY occurred_at DESC, id DESC');
            }))
            ->willReturn($this->statement);

        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([]);

        $dto = new DeliveryOperationsQueryDTO(
            actorType: 'SYS',
            targetType: 'DOC',
            channel: 'EMAIL'
        );

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $repository->find($dto);
    }

    public function testPrimitiveCursorRequiresBothParts(): void
    {
        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);

        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                $this->assertStringNotContainsString(':cursor_at', $sql);
                $this->assertStringNotContainsString(':cursor_id', $sql);
                return $this->statement;
            });

        $this->statement->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $params) {
                $this->assertArrayNotHasKey('cursor_at', $params);
                $this->assertArrayNotHasKey('cursor_id', $params);
                return true;
            });

        $this->statement->method('fetchAll')->willReturn([]);

        $repository->find(new DeliveryOperationsQueryDTO(
            cursorOccurredAt: new DateTimeImmutable('2023-01-01', new DateTimeZone('UTC')),
            cursorId: null
        ));

        $repository->find(new DeliveryOperationsQueryDTO(
            cursorOccurredAt: null,
            cursorId: 1
        ));
    }

    public function testPrimitiveOrderIsOccuredAtDescIdDesc(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                return str_contains($sql, 'ORDER BY occurred_at DESC, id DESC');
            }))
            ->willReturn($this->statement);

        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $repository->find(new DeliveryOperationsQueryDTO());
    }

    public function testPrimitiveLimitAboveMaxPassesThrough(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                return str_contains($sql, 'LIMIT 500');
            }))
            ->willReturn($this->statement);

        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $repository->find(new DeliveryOperationsQueryDTO(limit: 500));
    }

    public function testPrimitiveLimitZeroOrNegativeClampsToOne(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                return str_contains($sql, 'LIMIT 1');
            }))
            ->willReturn($this->statement);

        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $repository->find(new DeliveryOperationsQueryDTO(limit: 0));
    }

    public function testPrimitiveRepositoryDoesNotManageTransactions(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $repository->find(new DeliveryOperationsQueryDTO());
    }

    public function testPrimitiveRepositoryPDOExceptionPrevious(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('PDO failure'));

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);

        try {
            $repository->find(new DeliveryOperationsQueryDTO());
            $this->fail('Expected exception');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
        }
    }

    public function testPrimitiveRepositoryMappingExceptionPrefix(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            ['occurred_at' => 'invalid-date']
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);

        try {
            $repository->find(new DeliveryOperationsQueryDTO());
            $this->fail('Expected exception');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to map DeliveryOperations row:', $e->getMessage());
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
        }
    }

    // --- Primitive Hydration Parity ---

    public function testHydrationStringKeyMetadataObjectToResult(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'metadata' => '{"foo": "bar"}',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertSame(['foo' => 'bar'], $results[0]->metadata);
    }

    public function testHydrationEmptyDecodedArrayToEmptyArray(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'metadata' => '{}',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertSame([], $results[0]->metadata);
    }

    public function testHydrationMalformedJsonToNull(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'metadata' => 'not-json',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertNull($results[0]->metadata);
    }

    public function testHydrationScalarJsonToNull(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'metadata' => '"string"',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertNull($results[0]->metadata);
    }

    public function testHydrationNumericKeyObjectToNull(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'metadata' => '{"1": "value"}',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertNull($results[0]->metadata);
    }

    public function testHydrationMixedKeyObjectToNull(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'metadata' => '{"foo": "bar", "1": "baz"}',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertNull($results[0]->metadata);
    }

    public function testHydrationNonEmptyListToNull(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'metadata' => '[1, 2, 3]',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertNull($results[0]->metadata);
    }

    public function testHydrationMissingOccurredAtProducesEpochUtc(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's',
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $this->assertSame('1970-01-01T00:00:00+00:00', $results[0]->occurredAt->format(\DATE_ATOM));
    }

    public function testHydrationNullOptionalFieldsPreserveNull(): void
    {
        $this->pdo->method('prepare')->willReturn($this->statement);
        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('fetchAll')->willReturn([
            [
                'id' => '1', 'event_id' => 'e', 'channel' => 'c', 'operation_type' => 'o',
                'status' => 's', 'occurred_at' => '2023-01-01 00:00:00.000000',
                'actor_type' => null, 'actor_id' => null, 'target_type' => null, 'target_id' => null,
                'scheduled_at' => null, 'completed_at' => null, 'correlation_id' => null,
                'request_id' => null, 'provider' => null, 'provider_message_id' => null,
                'error_code' => null, 'error_message' => null,
            ]
        ]);

        $repository = new DeliveryOperationsQueryMysqlRepository($this->pdo);
        $results = $repository->find(new DeliveryOperationsQueryDTO());
        $dto = $results[0];
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertNull($dto->scheduledAt);
        $this->assertNull($dto->completedAt);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->provider);
        $this->assertNull($dto->providerMessageId);
        $this->assertNull($dto->errorCode);
        $this->assertNull($dto->errorMessage);
    }

    // --- Admin Query Repository Behavior ---

    public function testAdminRepositoryImplementsCorrectInterface(): void
    {
        $this->assertInstanceOf(DeliveryOperationsAdminQueryInterface::class, new DeliveryOperationsAdminQueryMysqlRepository($this->pdo));
    }

    public function testAdminRepositoryDoesNotManageTransactions(): void
    {
        $this->pdo->expects($this->never())->method('beginTransaction');
        $this->pdo->expects($this->never())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')
            ->willReturnCallback(function (string $sql) {
                return str_contains($sql, 'COUNT(*)') ? $this->countStatement : $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 0], false, ['COUNT(*)' => 0], false);
        $this->countStatement->method('errorCode')->willReturn('00000');
        $this->statement->method('errorCode')->willReturn('00000');

        $repository = new DeliveryOperationsAdminQueryMysqlRepository($this->pdo);
        $repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
    }

    public function testAdminRepositoryPDOExceptionPrevious(): void
    {
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Connection failed'));

        $repository = new DeliveryOperationsAdminQueryMysqlRepository($this->pdo);

        try {
            $repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
            $this->fail('Expected exception');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
            $this->assertStringContainsString('Failed to query DeliveryOperations records:', $e->getMessage());
        }
    }

    public function testAdminRepositoryMappingExceptionPrefix(): void
    {
        $this->pdo->method('prepare')
            ->willReturnCallback(function (string $sql) {
                return str_contains($sql, 'COUNT(*)') ? $this->countStatement : $this->statement;
            });

        $this->countStatement->method('execute')->willReturn(true);
        $this->countStatement->method('bindValue')->willReturn(true);
        $this->countStatement->method('columnCount')->willReturn(1);
        $this->countStatement->method('fetch')
            ->willReturnOnConsecutiveCalls(['COUNT(*)' => 1], false, ['COUNT(*)' => 1], false);
        $this->countStatement->method('errorCode')->willReturn('00000');

        $this->statement->method('execute')->willReturn(true);
        $this->statement->method('bindValue')->willReturn(true);
        $this->statement->method('errorCode')->willReturn('00000');
        $this->statement->method('fetch')->willReturn([
            'id' => '1', 'occurred_at' => 'invalid-date'
        ]);

        $repository = new DeliveryOperationsAdminQueryMysqlRepository($this->pdo);

        try {
            $repository->paginate(new DeliveryOperationsAdminQueryRequestDTO());
            $this->fail('Expected exception');
        } catch (DeliveryOperationsStorageException $e) {
            $this->assertStringContainsString('Failed to map DeliveryOperations row:', $e->getMessage());
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
        }
    }

    // --- Protected DeliveryOperations Boundary ---

    public function testRecorderSuccessCallsWriterWithDto(): void
    {
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $clock = new SystemClock();
        $policy = new DeliveryOperationsDefaultPolicy();
        $recorder = new DeliveryOperationsRecorder($writer, $clock, null, $policy);

        $capturedDto = null;
        $writer->expects($this->once())
            ->method('log')
            ->willReturnCallback(function (DeliveryOperationRecordDTO $dto) use (&$capturedDto) {
                $capturedDto = $dto;
            });

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            attemptNo: 1,
            actorType: 'SYSTEM',
            targetType: 'USER',
            targetId: 1
        );

        $this->assertInstanceOf(DeliveryOperationRecordDTO::class, $capturedDto);
        $this->assertSame('EMAIL', $capturedDto->channel);
        $this->assertSame('NOTIFICATION', $capturedDto->operationType);
        $this->assertSame('SENT', $capturedDto->status);
        $this->assertSame(1, $capturedDto->attemptNo);
        $this->assertSame('SYSTEM', $capturedDto->actorType);
        $this->assertSame('USER', $capturedDto->targetType);
        $this->assertSame(1, $capturedDto->targetId);
        $this->assertInstanceOf(DateTimeImmutable::class, $capturedDto->occurredAt);
    }

    public function testRecorderFailOpenBoundary(): void
    {
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $writer->method('log')->willThrowException(new PDOException('storage failure'));

        $clock = new SystemClock();
        $policy = new DeliveryOperationsDefaultPolicy();
        $recorder = new DeliveryOperationsRecorder($writer, $clock, null, $policy);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            attemptNo: 1,
            actorType: 'SYSTEM',
            targetType: 'USER',
            targetId: 1
        );

        // If we reach this line, no exception escaped — fail-open is working
        $this->addToAssertionCount(1);
    }

    public function testRecorderFailOpenWithFallbackLoggerReceivesError(): void
    {
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $writer->method('log')->willThrowException(new PDOException('storage failure'));

        $fallbackLogger = $this->createMock(LoggerInterface::class);
        $fallbackLogger->expects($this->once())
            ->method('error')
            ->with(
                'DeliveryOperations logging failed',
                $this->callback(function (array $context) {
                    return str_contains($context['exception'], 'storage failure');
                })
            );

        $clock = new SystemClock();
        $policy = new DeliveryOperationsDefaultPolicy();
        $recorder = new DeliveryOperationsRecorder($writer, $clock, $fallbackLogger, $policy);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            attemptNo: 1,
            actorType: 'SYSTEM',
            targetType: 'USER',
            targetId: 1
        );
    }

    public function testRecorderFallbackLoggerFailureDoesNotEscape(): void
    {
        $writer = $this->createMock(DeliveryOperationsLoggerInterface::class);
        $writer->method('log')->willThrowException(new PDOException('storage failure'));

        $fallbackLogger = $this->createMock(LoggerInterface::class);
        $fallbackLogger->method('error')->willThrowException(new \RuntimeException('logger also broken'));

        $clock = new SystemClock();
        $policy = new DeliveryOperationsDefaultPolicy();
        $recorder = new DeliveryOperationsRecorder($writer, $clock, $fallbackLogger, $policy);

        $recorder->record(
            channel: DeliveryChannelEnum::EMAIL,
            operationType: DeliveryOperationTypeEnum::NOTIFICATION,
            status: DeliveryStatusEnum::SENT,
            attemptNo: 1,
            actorType: 'SYSTEM',
            targetType: 'USER',
            targetId: 1
        );

        // If we reach this line, neither writer failure nor fallback logger failure escaped
        $this->addToAssertionCount(1);
    }

    public function testPolicyNormalizationKnownType(): void
    {
        $policy = new DeliveryOperationsDefaultPolicy();
        $this->assertSame('SYSTEM', $policy->normalizeActorType('system'));
    }

    public function testPolicyNormalizationUnknownType(): void
    {
        $policy = new DeliveryOperationsDefaultPolicy();
        $this->assertSame('ANYTHING', $policy->normalizeActorType('anything'));
    }

    public function testPolicyMetadataSizeValidation(): void
    {
        $policy = new DeliveryOperationsDefaultPolicy();
        $validMeta = json_encode(['key' => 'value']);
        $result = $policy->validateMetadataSize($validMeta);
        $this->assertTrue($result);
    }

    public function testFactoryCreatesRecorderWithRealDependencies(): void
    {
        $factory = new DeliveryOperationsFactory();
        $this->assertInstanceOf(DeliveryOperationsFactory::class, $factory);

        $pdo = $this->createMock(PDO::class);
        $clock = new SystemClock();

        $recorder = DeliveryOperationsFactory::create($pdo, $clock);
        $this->assertInstanceOf(DeliveryOperationsRecorder::class, $recorder);
    }

    public function testProviderReturnsRecorderInstance(): void
    {
        $pdo = $this->createMock(PDO::class);
        $clock = new SystemClock();
        $provider = EventLoggingProviderFactory::createDefault($pdo, $clock);

        $this->assertInstanceOf(EventLoggingProvider::class, $provider);
        $this->assertInstanceOf(DeliveryOperationsRecorder::class, $provider->deliveryOperations());
    }

    public function testBindingsDefinitionsIncludeDeliveryOperations(): void
    {
        $definitions = EventLoggingBindings::definitions();

        $this->assertArrayHasKey(DeliveryOperationsRecorder::class, $definitions);
        $this->assertArrayHasKey(DeliveryOperationsQueryInterface::class, $definitions);

        $recorderFactory = $definitions[DeliveryOperationsRecorder::class];
        $this->assertInstanceOf(\Closure::class, $recorderFactory);

        $queryFactory = $definitions[DeliveryOperationsQueryInterface::class];
        $this->assertInstanceOf(\Closure::class, $queryFactory);
    }

    public function testSchemaContractExistsWithColumnsAndIndexes(): void
    {
        $schemaPath = __DIR__ . '/../../../../../src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql';
        $this->assertFileExists($schemaPath);
        $schema = file_get_contents($schemaPath);

        $this->assertStringContainsString('maa_event_logging_delivery_operations', $schema);

        // Validate required columns exist
        $requiredColumns = [
            'id', 'event_id', 'channel', 'operation_type', 'actor_type', 'actor_id',
            'target_type', 'target_id', 'status', 'attempt_no', 'scheduled_at',
            'completed_at', 'correlation_id', 'request_id', 'provider', 'provider_message_id',
            'error_code', 'error_message', 'metadata', 'occurred_at',
        ];
        foreach ($requiredColumns as $col) {
            $this->assertStringContainsString($col, $schema, "Schema missing column: {$col}");
        }

        // Validate PK
        $this->assertStringContainsString('PRIMARY KEY', $schema);

        // Validate unique key on event_id
        $this->assertStringContainsString('UNIQUE KEY', $schema);
        $this->assertStringContainsString('event_id', $schema);

        // Validate required indexes exist
        $this->assertStringContainsString('INDEX', $schema);
        $this->assertStringContainsString('occurred_at', $schema);
    }

    public function testDeliveryOperationsViewDtoHasAllTwentyFields(): void
    {
        $ref = new \ReflectionClass(DeliveryOperationsViewDTO::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(20, $constructor->getParameters());
    }

    public function testAdminQueryResultDtoHasTenFields(): void
    {
        $ref = new \ReflectionClass(DeliveryOperationsAdminPageResultDTO::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(10, $constructor->getParameters());
    }

    public function testAdminQueryRequestDtoSerializationOrder(): void
    {
        $dto = new DeliveryOperationsAdminQueryRequestDTO(channel: 'EMAIL');
        $serialized = $dto->jsonSerialize();
        $keys = array_keys($serialized);
        $this->assertSame('page', $keys[0]);
        $this->assertSame('perPage', $keys[1]);
        $this->assertSame('sortBy', $keys[2]);
    }

    public function testWriterRepositorySendsCorrectSqlAndParameters(): void
    {
        $writerPdo = $this->createMock(PDO::class);
        $writerStmt = $this->createMock(PDOStatement::class);

        $writerPdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                return str_contains($sql, 'INSERT INTO maa_event_logging_delivery_operations')
                    && str_contains($sql, ':event_id')
                    && str_contains($sql, ':channel')
                    && str_contains($sql, ':metadata')
                    && str_contains($sql, ':occurred_at');
            }))
            ->willReturn($writerStmt);

        $writerStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) {
                return array_key_exists(':event_id', $params)
                    && array_key_exists(':channel', $params)
                    && array_key_exists(':metadata', $params)
                    && array_key_exists(':occurred_at', $params);
            }))
            ->willReturn(true);

        $repository = new DeliveryOperationsLoggerMysqlRepository($writerPdo);
        $repository->log(new DeliveryOperationRecordDTO(
            eventId: 'test-event-id',
            channel: 'EMAIL',
            operationType: 'NOTIFICATION',
            actorType: 'SYSTEM',
            actorId: 1,
            targetType: 'USER',
            targetId: 2,
            status: 'SENT',
            attemptNo: 1,
            scheduledAt: null,
            completedAt: null,
            correlationId: null,
            requestId: null,
            provider: null,
            providerMessageId: null,
            errorCode: null,
            errorMessage: null,
            metadata: ['key' => 'value'],
            occurredAt: new DateTimeImmutable('2023-01-01 00:00:00', new DateTimeZone('UTC'))
        ));
    }
}
