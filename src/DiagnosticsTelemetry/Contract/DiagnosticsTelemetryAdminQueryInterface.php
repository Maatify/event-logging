<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Contract;

use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminPageResultDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryExecutionException;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryInvalidArgumentException;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;

interface DiagnosticsTelemetryAdminQueryInterface
{
    /**
     * @throws DiagnosticsTelemetryStorageException
     * @throws DiagnosticsTelemetryAdminQueryExecutionException
     * @throws DiagnosticsTelemetryAdminQueryInvalidArgumentException
     */
    public function paginate(DiagnosticsTelemetryAdminQueryRequestDTO $request): DiagnosticsTelemetryAdminPageResultDTO;
}
