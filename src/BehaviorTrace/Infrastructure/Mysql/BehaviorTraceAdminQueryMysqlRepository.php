<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql;

use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceAdminQueryInterface;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePolicyInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminPageResultDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryExecutionException;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\Pagination\BehaviorTraceAdminQueryDescriptorBuilder;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceDefaultPolicy;
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

final class BehaviorTraceAdminQueryMysqlRepository implements BehaviorTraceAdminQueryInterface
{
    private BehaviorTraceRowMapper $mapper;

    private BehaviorTraceAdminQueryDescriptorBuilder $descriptorBuilder;

    private PdoPaginator $paginator;

    public function __construct(
        private PDO $pdo,
        ?BehaviorTracePolicyInterface $policy = null,
    ) {
        $effectivePolicy = $policy ?? new BehaviorTraceDefaultPolicy();

        $this->mapper = new BehaviorTraceRowMapper($effectivePolicy);
        $this->descriptorBuilder = new BehaviorTraceAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();
    }

    public function paginate(
        BehaviorTraceAdminQueryRequestDTO $request,
    ): BehaviorTraceAdminPageResultDTO {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection,
        );

        try {
            $descriptor = $this->descriptorBuilder->build($request);
            $paginationConfig = $this->createPaginationConfig();

            $result = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $paginationConfig,
                fn (array $row): BehaviorTraceEventDTO => $this->mapRow($row),
            );
        } catch (PaginationExecutionException | PDOException $exception) {
            throw new BehaviorTraceStorageException(
                message: 'Failed to query BehaviorTrace records: '
                    . $exception->getMessage(),
                previous: $exception,
            );
        } catch (
            InvalidPaginationConfigurationException
            | InvalidPaginationQueryException $exception
        ) {
            throw BehaviorTraceAdminQueryExecutionException::executionFailed(
                $exception,
            );
        }

        return new BehaviorTraceAdminPageResultDTO(
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
    private function mapRow(array $row): BehaviorTraceEventDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (\Exception $exception) {
            throw new BehaviorTraceStorageException(
                message: 'Failed to map BehaviorTrace row: '
                    . $exception->getMessage(),
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
