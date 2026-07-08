<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';

use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\DiagnosticsTelemetry\Command\RecordDiagnosticsTelemetryCommand;

/**
 * 07 - Diagnostics Telemetry Record
 *
 * Show how to record technical diagnostics telemetry.
 */

// We assume $pdo, $clock, and $logger are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\EventLogging\Common\ClockInterface $clock
// @var \Psr\Log\LoggerInterface $logger

$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $logger);
$diagnosticsTelemetry = $provider->diagnosticsTelemetry();

$command = new RecordDiagnosticsTelemetryCommand(
    eventKey: 'cache_miss',
    severity: 'LOW',
    actorType: 'system',
    actorId: null,
    durationMs: 150
);

$diagnosticsTelemetry->recordCommand($command);

echo "Recorded diagnostics telemetry event.\n";
