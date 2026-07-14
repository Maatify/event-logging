<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Infrastructure\Mysql;

use Maatify\EventLogging\AuditTrail\Contract\AuditTrailAdminQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminPageResultDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryExecutionException;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\Pagination\AuditTrailAdminQueryDescriptorBuilder;
use Maatify\Persistence\Exception\InvalidPaginationConfigurationException;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;
use PDOException;

final class AuditTrailAdminQueryMysqlRepository implements AuditTrailAdminQueryInterface
{
    private AuditTrailRowMapper $mapper;
    private AuditTrailAdminQueryDescriptorBuilder $descriptorBuilder;
    private PdoPaginator $paginator;

    public function __construct(private PDO $pdo)
    {
        $this->mapper = new AuditTrailRowMapper();
        $this->descriptorBuilder = new AuditTrailAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();
    }

    public function paginate(AuditTrailAdminQueryRequestDTO $request): AuditTrailAdminPageResultDTO
    {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection
        );

        try {
            $descriptor = $this->descriptorBuilder->build($request);
            $paginationConfig = $this->createPaginationConfig();

            $result = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $paginationConfig,
                fn (array $row): AuditTrailViewDTO => $this->mapper->map($row),
            );
        } catch (PaginationExecutionException | PDOException $e) {
            throw new AuditTrailStorageException(
                message: 'Failed to query audit trail: ' . $e->getMessage(),
                previous: $e
            );
        } catch (InvalidPaginationConfigurationException | InvalidPaginationQueryException $e) {
            throw AuditTrailAdminQueryExecutionException::executionFailed($e);
        }

        return new AuditTrailAdminPageResultDTO(
            items: $result->data,
            page: $result->page,
            perPage: $result->perPage,
            total: $result->total,
            filtered: $result->filtered,
            totalPages: $result->totalPages,
            hasNext: $result->hasNext,
            hasPrevious: $result->hasPrevious,
            sortBy: $result->sortBy,
            sortDirection: $result->sortDirection->value
        );
    }

    private function createPaginationConfig(): PaginationConfig
    {
        return new PaginationConfig(
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
    }
}
