<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Infrastructure\Mysql;

use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class BehaviorTraceAdminQueryMysqlRepositoryTest extends TestCase
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

    public function testPdoExceptionIsTranslatedToStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $previous = new PDOException('database down');
        $pdo->method('prepare')->willThrowException($previous);

        $repository = new BehaviorTraceAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new BehaviorTraceAdminQueryRequestDTO());
            $this->fail('Expected storage exception.');
        } catch (BehaviorTraceStorageException $exception) {
            $this->assertSame('Failed to query BehaviorTrace records: database down', $exception->getMessage());
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function testPaginationExecutionExceptionIsTranslatedToStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn(false);

        $repository = new BehaviorTraceAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new BehaviorTraceAdminQueryRequestDTO());
            self::fail('Expected BehaviorTraceStorageException.');
        } catch (BehaviorTraceStorageException $exception) {
            self::assertSame(
                'Failed to query BehaviorTrace records: Failed to prepare pagination query.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(PaginationExecutionException::class, $exception->getPrevious());
        }
    }
}
