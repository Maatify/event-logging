<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql;

use Closure;
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
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
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

    /** @var Closure(array<string, mixed>): AuthoritativeAuditViewDTO */
    private Closure $rowMapperCallable;

    /** @var Closure(AuthoritativeAuditAdminQueryRequestDTO): PdoPaginationQueryDescriptor */
    private Closure $descriptorBuilderCallable;

    public function __construct(private PDO $pdo)
    {
        $this->mapper = new AuthoritativeAuditRowMapper();
        $this->descriptorBuilder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();
        $this->rowMapperCallable = $this->mapper->map(...);
        $this->descriptorBuilderCallable = $this->descriptorBuilder->build(...);
    }

    public function paginate(AuthoritativeAuditAdminQueryRequestDTO $request): AuthoritativeAuditAdminPageResultDTO
    {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection,
        );

        try {
            $descriptorBuilder = $this->descriptorBuilderCallable;
            $result = $this->paginator->paginate(
                $this->pdo,
                $descriptorBuilder($request),
                $pageRequest,
                $this->createPaginationConfig(),
                fn (array $row): AuthoritativeAuditViewDTO => $this->mapRow($row),
            );
        } catch (PaginationExecutionException | PDOException $exception) {
            throw new AuthoritativeAuditStorageException(
                message: 'Failed to query AuthoritativeAudit records: ' . $exception->getMessage(),
                previous: $exception,
            );
        } catch (InvalidPaginationConfigurationException | InvalidPaginationQueryException $exception) {
            throw AuthoritativeAuditAdminQueryExecutionException::executionFailed($exception);
        }

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
            sortDirection: $result->sortDirection->value,
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

    /**
     * @param array<string, mixed> $row
     * @throws AuthoritativeAuditStorageException
     */
    private function mapRow(array $row): AuthoritativeAuditViewDTO
    {
        try {
            $mapper = $this->rowMapperCallable;
            return $mapper($row);
        } catch (AuthoritativeAuditStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AuthoritativeAuditStorageException(
                message: 'Failed to map AuthoritativeAudit row: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
