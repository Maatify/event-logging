<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Infrastructure\Mysql;

use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SecuritySignalsAdminQueryMysqlRepositoryTest extends TestCase
{
    public function testConstructorIsPdoOnlyAndCanonicalPaginationConfigurationIsConstructible(): void
    {
        $constructor = new ReflectionMethod(SecuritySignalsAdminQueryMysqlRepository::class, '__construct');
        $this->assertCount(1, $constructor->getParameters());
        $this->assertSame('pdo', $constructor->getParameters()[0]->getName());
        $this->assertSame('PDO', (string) $constructor->getParameters()[0]->getType());

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

    public function testPdoExceptionIsTranslatedToStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $previous = new PDOException('database down');
        $pdo->method('prepare')->willThrowException($previous);

        $repository = new SecuritySignalsAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new SecuritySignalsAdminQueryRequestDTO());
            $this->fail('Expected storage exception.');
        } catch (SecuritySignalsStorageException $exception) {
            $this->assertSame('Failed to query SecuritySignals records: database down', $exception->getMessage());
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function testPaginationExecutionExceptionIsTranslatedToStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn(false);

        $repository = new SecuritySignalsAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new SecuritySignalsAdminQueryRequestDTO());
            self::fail('Expected SecuritySignalsStorageException.');
        } catch (SecuritySignalsStorageException $exception) {
            self::assertSame(
                'Failed to query SecuritySignals records: Failed to prepare pagination query.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(PaginationExecutionException::class, $exception->getPrevious());
        }
    }
}
