<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\Enum;

interface DiagnosticsTelemetrySeverityInterface
{
    public function value(): string;
}
