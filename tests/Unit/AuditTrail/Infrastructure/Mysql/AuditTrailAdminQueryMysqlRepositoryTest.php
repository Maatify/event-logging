<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Infrastructure\Mysql;

use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailAdminQueryMysqlRepository;
use Maatify\Persistence\Exception\PaginationExecutionException;
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

    public function testPaginationExecutionExceptionIsTranslatedToAuditTrailStorageExceptionWithPrevious(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn(false);

        $repository = new AuditTrailAdminQueryMysqlRepository($pdo);

        try {
            $repository->paginate(new AuditTrailAdminQueryRequestDTO());
            self::fail('Expected AuditTrailStorageException.');
        } catch (AuditTrailStorageException $exception) {
            self::assertSame(
                'Failed to query audit trail: Failed to prepare pagination query.',
                $exception->getMessage(),
            );

            self::assertInstanceOf(
                PaginationExecutionException::class,
                $exception->getPrevious(),
            );
        }
    }
}
