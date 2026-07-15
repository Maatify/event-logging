<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql;

use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsAdminQueryInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminPageResultDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryExecutionException;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\Pagination\SecuritySignalsAdminQueryDescriptorBuilder;
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

final class SecuritySignalsAdminQueryMysqlRepository implements SecuritySignalsAdminQueryInterface
{
    private SecuritySignalsRowMapper $mapper;
    private SecuritySignalsAdminQueryDescriptorBuilder $descriptorBuilder;
    private PdoPaginator $paginator;

    public function __construct(private PDO $pdo)
    {
        $this->mapper = new SecuritySignalsRowMapper();
        $this->descriptorBuilder = new SecuritySignalsAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();
    }

    public function paginate(
        SecuritySignalsAdminQueryRequestDTO $request,
    ): SecuritySignalsAdminPageResultDTO {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection,
        );

        try {
            $result = $this->paginator->paginate(
                $this->pdo,
                $this->descriptorBuilder->build($request),
                $pageRequest,
                $this->createPaginationConfig(),
                fn (array $row): SecuritySignalsViewDTO => $this->mapRow($row),
            );
        } catch (PaginationExecutionException | PDOException $exception) {
            throw new SecuritySignalsStorageException(
                message: 'Failed to query SecuritySignals records: ' . $exception->getMessage(),
                previous: $exception,
            );
        } catch (
            InvalidPaginationConfigurationException
            | InvalidPaginationQueryException $exception
        ) {
            throw SecuritySignalsAdminQueryExecutionException::executionFailed($exception);
        }

        return new SecuritySignalsAdminPageResultDTO(
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

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): SecuritySignalsViewDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (SecuritySignalsStorageException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new SecuritySignalsStorageException(
                message: 'Failed to map SecuritySignals row: ' . $exception->getMessage(),
                previous: $exception,
            );
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
}
