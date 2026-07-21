<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Infrastructure\Mysql;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Exception\PaginationExecutionException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest extends TestCase
{
    private ?Throwable $mapperException = null;

    public function testPdoExceptionIsWrappedInStorageException(): void
    {
        $originalException = new PDOException('DB Error');
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException($originalException);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame('Failed to query AuthoritativeAudit records: DB Error', $exception->getMessage());
            self::assertSame($originalException, $exception->getPrevious());
        }
    }

    public function testPaginationExecutionExceptionIsWrappedInStorageException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn(false);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame(
                'Failed to query AuthoritativeAudit records: Failed to prepare pagination query.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(PaginationExecutionException::class, $exception->getPrevious());
        }
    }

    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $originalException = new InvalidPaginationQueryException('Injected descriptor error');
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->createMock(PDO::class));
        $reflection = new ReflectionClass(AuthoritativeAuditAdminQueryMysqlRepository::class);
        $reflection->getProperty('descriptorBuilderCallable')->setValue(
            $repository,
            static function (AuthoritativeAuditAdminQueryRequestDTO $request) use ($originalException): never {
                unset($request);
                throw $originalException;
            },
        );

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
            self::fail('Expected AuthoritativeAuditAdminQueryExecutionException was not thrown.');
        } catch (AuthoritativeAuditAdminQueryExecutionException $exception) {
            self::assertSame($originalException, $exception->getPrevious());
        }
    }

    public function testMapperThrowableIsWrappedInStorageException(): void
    {
        $originalException = new RuntimeException('Mapper exploded');
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->createMock(PDO::class));
        $reflection = new ReflectionClass(AuthoritativeAuditAdminQueryMysqlRepository::class);
        $this->mapperException = $originalException;
        $reflection->getProperty('rowMapperCallable')->setValue(
            $repository,
            $this->throwMapperException(...),
        );

        try {
            $reflection->getMethod('mapRow')->invoke($repository, [
                'event_id' => 'event-1',
                'occurred_at' => '2024-01-01 00:00:00.000000',
            ]);
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame('Failed to map AuthoritativeAudit row: Mapper exploded', $exception->getMessage());
            self::assertSame($originalException, $exception->getPrevious());
        }
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $originalException = new AuthoritativeAuditStorageException('Original storage exception');
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($this->createMock(PDO::class));
        $reflection = new ReflectionClass(AuthoritativeAuditAdminQueryMysqlRepository::class);
        $this->mapperException = $originalException;
        $reflection->getProperty('rowMapperCallable')->setValue(
            $repository,
            $this->throwMapperException(...),
        );

        try {
            $reflection->getMethod('mapRow')->invoke($repository, [
                'event_id' => 'event-1',
                'occurred_at' => '2024-01-01 00:00:00.000000',
            ]);
            self::fail('Expected AuthoritativeAuditStorageException was not thrown.');
        } catch (AuthoritativeAuditStorageException $exception) {
            self::assertSame($originalException, $exception);
        }
    }

    /** @param array<string, mixed> $row */
    private function throwMapperException(array $row): never
    {
        unset($row);

        $exception = $this->mapperException;
        if (!$exception instanceof Throwable) {
            self::fail('Mapper exception test seam was not initialized.');
        }

        throw $exception;
    }
}
