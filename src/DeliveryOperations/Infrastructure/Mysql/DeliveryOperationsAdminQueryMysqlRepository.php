<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql;

use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsAdminQueryInterface;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminPageResultDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsViewDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryExecutionException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\Pagination\DeliveryOperationsAdminQueryDescriptorBuilder;
use Maatify\Persistence\Exception\InvalidPaginationConfigurationException;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;

final class DeliveryOperationsAdminQueryMysqlRepository implements DeliveryOperationsAdminQueryInterface
{
    private PdoPaginator $paginator;
    private DeliveryOperationsRowMapper $mapper;
    private DeliveryOperationsAdminQueryDescriptorBuilder $descriptorBuilder;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->paginator = new PdoPaginator();
        $this->mapper = new DeliveryOperationsRowMapper();
        $this->descriptorBuilder = new DeliveryOperationsAdminQueryDescriptorBuilder();
    }

    public function paginate(DeliveryOperationsAdminQueryRequestDTO $request): DeliveryOperationsAdminPageResultDTO
    {
        try {
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

            $pageRequest = new PageRequest(
                page: $request->page,
                perPage: $request->perPage,
                sortBy: $request->sortBy,
                sortDirection: $request->sortDirection
            );

            $descriptor = $this->descriptorBuilder->build($request);

            $pageResult = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $config,
                fn(array $row) => $this->mapRow($row)
            );

            return new DeliveryOperationsAdminPageResultDTO(
                $pageResult->data,
                $pageResult->page,
                $pageResult->perPage,
                $pageResult->total,
                $pageResult->filtered,
                $pageResult->totalPages,
                $pageResult->hasNext,
                $pageResult->hasPrevious,
                $pageResult->sortBy,
                $pageResult->sortDirection->value
            );
        } catch (InvalidPaginationConfigurationException|InvalidPaginationQueryException $e) {
            throw DeliveryOperationsAdminQueryExecutionException::executionFailed($e);
        } catch (PaginationExecutionException|\PDOException $e) {
            throw new DeliveryOperationsStorageException('Failed to query DeliveryOperations records: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return DeliveryOperationsViewDTO
     * @throws DeliveryOperationsStorageException
     */
    private function mapRow(array $row): DeliveryOperationsViewDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (DeliveryOperationsStorageException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DeliveryOperationsStorageException('Failed to map DeliveryOperations row: ' . $e->getMessage(), previous: $e);
        }
    }
}
