<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Infrastructure\Mysql;

use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryAdminQueryMysqlRepository
 */
final class DiagnosticsTelemetryAdminQueryMysqlRepositoryTest extends TestCase
{
    public function testRepositoryUsesCanonicalPaginationConfiguration(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new DiagnosticsTelemetryAdminQueryMysqlRepository($pdo);

        $reflector = new \ReflectionClass($repository);
        $method = $reflector->getMethod('createPaginationConfig');

        /** @var PaginationConfig $config */
        $config = $method->invoke($repository);

        $this->assertSame('occurred_at', $config->defaultSortBy);
        $this->assertSame(SortDirectionEnum::DESC, $config->defaultSortDirection);
        $this->assertSame('id', $config->tieBreakerSortBy);
        $this->assertSame(SortDirectionEnum::DESC, $config->tieBreakerDirection);
        $this->assertSame(20, $config->defaultPerPage);
        $this->assertSame(1, $config->minPerPage);
        $this->assertSame(200, $config->maxPerPage);
        $this->assertSame('`occurred_at`', $config->sortWhitelist->quotedIdentifierFor('occurred_at'));
        $this->assertSame('`id`', $config->sortWhitelist->quotedIdentifierFor('id'));
    }

    public function testPdoExceptionIsTranslatedToStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $previous = new PDOException('database down');
        $pdo->method('prepare')->willThrowException($previous);

        $repository = new DiagnosticsTelemetryAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO());
            $this->fail('Expected storage exception.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            $this->assertSame('Failed to query DiagnosticsTelemetry records: database down', $exception->getMessage());
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function testPaginationExecutionExceptionIsTranslatedToStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn(false);

        $repository = new DiagnosticsTelemetryAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO());
            self::fail('Expected DiagnosticsTelemetryStorageException.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            self::assertSame(
                'Failed to query DiagnosticsTelemetry records: Failed to prepare pagination query.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(PaginationExecutionException::class, $exception->getPrevious());
        }
    }

    public function testMapperPolicyFailureIsTranslatedToStorageExceptionAndPreservesPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $policy = new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface {
            public function normalizeSeverity(string|\Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface $severity): \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface
            {
                throw new \Exception('Simulated policy failure');
            }
            public function normalizeActorType(string|\Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface $actorType): \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface
            {
                throw new \Exception('Simulated policy failure');
            }
        };

        $repository = new DiagnosticsTelemetryAdminQueryMysqlRepository($pdo, $policy);

        $reflector = new \ReflectionClass($repository);
        $method = $reflector->getMethod('mapRow');

        try {
            $method->invoke($repository, ['severity' => 'INFO']);
            $this->fail('Expected DiagnosticsTelemetryStorageException.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            $this->assertSame('Failed to map DiagnosticsTelemetry row: Simulated policy failure', $exception->getMessage());
            $this->assertInstanceOf(\Exception::class, $exception->getPrevious());
            $this->assertSame('Simulated policy failure', $exception->getPrevious()->getMessage());
        }
    }

    public function testMapperStorageExceptionIsPropagatedUnchanged(): void
    {
        $pdo = $this->createMock(PDO::class);
        $policy = new class implements \Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface {
            public function normalizeSeverity(string|\Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface $severity): \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface
            {
                throw new DiagnosticsTelemetryStorageException('Already a storage exception');
            }
            public function normalizeActorType(string|\Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface $actorType): \Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface
            {
                throw new DiagnosticsTelemetryStorageException('Already a storage exception');
            }
        };

        $repository = new DiagnosticsTelemetryAdminQueryMysqlRepository($pdo, $policy);

        $reflector = new \ReflectionClass($repository);
        $method = $reflector->getMethod('mapRow');

        try {
            $method->invoke($repository, ['severity' => 'INFO']);
            $this->fail('Expected DiagnosticsTelemetryStorageException.');
        } catch (DiagnosticsTelemetryStorageException $exception) {
            $this->assertSame('Already a storage exception', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
