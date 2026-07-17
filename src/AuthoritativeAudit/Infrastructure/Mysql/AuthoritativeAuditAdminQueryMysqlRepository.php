<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql;

use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditAdminQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder;
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
use Throwable;

final class AuthoritativeAuditAdminQueryMysqlRepository implements AuthoritativeAuditAdminQueryInterface
{
    private AuthoritativeAuditRowMapper $mapper;
    private AuthoritativeAuditAdminQueryDescriptorBuilder $descriptorBuilder;
    private PdoPaginator $paginator;
    /** @var \Closure(\PDO, \Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor, \Maatify\Persistence\Pdo\Pagination\PageRequest, \Maatify\Persistence\Pdo\Pagination\PaginationConfig, callable): \Maatify\Persistence\Pdo\Pagination\PageResult<\Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO> */
    private \Closure $paginateExecutionCallable;

    public function __construct(private PDO $pdo)
    {
        $this->mapper = new AuthoritativeAuditRowMapper();
        $this->descriptorBuilder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();
        $this->paginateExecutionCallable = function (\PDO $pdo, \Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor $query, \Maatify\Persistence\Pdo\Pagination\PageRequest $pageRequest, \Maatify\Persistence\Pdo\Pagination\PaginationConfig $config, callable $mapper): \Maatify\Persistence\Pdo\Pagination\PageResult {
            /** @var callable(array<string, mixed>): \Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO $mapperTyped */
            $mapperTyped = $mapper;
            return $this->paginator->paginate($pdo, $query, $pageRequest, $config, $mapperTyped);
        };
    }

    public function paginate(AuthoritativeAuditAdminQueryRequestDTO $request): AuthoritativeAuditAdminPageResultDTO
    {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection
        );

        try {
            $mapperCallable = function (array $row): AuthoritativeAuditViewDTO {
                /** @var array<string, mixed> $row */
                return $this->mapRow($row);
            };
            $callable = $this->paginateExecutionCallable;
            /** @var \Maatify\Persistence\Pdo\Pagination\PageResult<AuthoritativeAuditViewDTO> $result */
            $result = $callable(
                $this->pdo,
                $this->descriptorBuilder->build($request),
                $pageRequest,
                $this->createPaginationConfig(),
                $mapperCallable
            );

            return new AuthoritativeAuditAdminPageResultDTO(
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
        } catch (InvalidPaginationConfigurationException | InvalidPaginationQueryException $exception) {
            throw AuthoritativeAuditAdminQueryExecutionException::executionFailed($exception);
        } catch (PaginationExecutionException | PDOException $exception) {
            throw new AuthoritativeAuditStorageException('Failed to query AuthoritativeAudit records: ' . $exception->getMessage(), 0, $exception);
        }
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

    /**
     * @param array<string, mixed> $row
     * @throws AuthoritativeAuditStorageException
     */
private function mapRow(array $row): AuthoritativeAuditViewDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (AuthoritativeAuditStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AuthoritativeAuditStorageException('Failed to map AuthoritativeAudit row: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
