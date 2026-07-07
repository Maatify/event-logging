<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Contract;

use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryCursorDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryQueryDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;

interface DiagnosticsTelemetryQueryInterface
{
    /**
     * @return array<DiagnosticsTelemetryEventDTO>
     * @throws DiagnosticsTelemetryStorageException
     */
    public function find(DiagnosticsTelemetryQueryDTO $query): array;

    /**
     * @param DiagnosticsTelemetryCursorDTO|null $cursor
     * @param int $limit
     * @return iterable<DiagnosticsTelemetryEventDTO>
     */
    public function read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100): iterable;
}
