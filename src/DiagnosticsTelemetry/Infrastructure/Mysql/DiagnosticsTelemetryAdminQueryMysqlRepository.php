<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql;

use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryAdminQueryInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminPageResultDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryExecutionException;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\Pagination\DiagnosticsTelemetryAdminQueryDescriptorBuilder;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryDefaultPolicy;
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

final class DiagnosticsTelemetryAdminQueryMysqlRepository implements DiagnosticsTelemetryAdminQueryInterface
{
    private DiagnosticsTelemetryRowMapper $mapper;
    private DiagnosticsTelemetryAdminQueryDescriptorBuilder $descriptorBuilder;
    private PdoPaginator $paginator;

    public function __construct(
        private readonly PDO $pdo,
        ?DiagnosticsTelemetryPolicyInterface $policy = null
    ) {
        $effectivePolicy = $policy ?? new DiagnosticsTelemetryDefaultPolicy();
        $this->mapper = new DiagnosticsTelemetryRowMapper($effectivePolicy);
        $this->descriptorBuilder = new DiagnosticsTelemetryAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();
    }

    public function paginate(DiagnosticsTelemetryAdminQueryRequestDTO $request): DiagnosticsTelemetryAdminPageResultDTO
    {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection
        );

        try {
            $result = $this->paginator->paginate(
                $this->pdo,
                $this->descriptorBuilder->build($request),
                $pageRequest,
                $this->createPaginationConfig(),
                fn (array $row): DiagnosticsTelemetryEventDTO => $this->mapRow($row)
            );

            return new DiagnosticsTelemetryAdminPageResultDTO(
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
            throw DiagnosticsTelemetryAdminQueryExecutionException::executionFailed($exception);
        } catch (PaginationExecutionException | PDOException $exception) {
            throw new DiagnosticsTelemetryStorageException('Failed to query DiagnosticsTelemetry records: ' . $exception->getMessage(), 0, $exception);
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
     * @throws DiagnosticsTelemetryStorageException
     */
    private function mapRow(array $row): DiagnosticsTelemetryEventDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (DiagnosticsTelemetryStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DiagnosticsTelemetryStorageException('Failed to map DiagnosticsTelemetry row: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
