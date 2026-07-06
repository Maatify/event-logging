<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Contract;

use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryEventDTO;

interface DiagnosticsTelemetryLoggerInterface
{
    public function write(DiagnosticsTelemetryEventDTO $dto): void;
}
