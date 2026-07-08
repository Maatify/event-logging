<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';
example_requires_pdo($pdo);

use Maatify\EventLogging\Provider\EventLoggingProviderFactory;

/**
 * 01 - Factory Provider Usage
 *
 * Demonstrates using EventLoggingProviderFactory to create the provider.
 * This sets up all domain recorders with the given dependencies.
 */

// We assume $pdo, $clock, and $logger are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\EventLogging\Common\ClockInterface $clock
// @var \Psr\Log\LoggerInterface $logger

$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $logger);

// Access a domain recorder
$auditTrailRecorder = $provider->auditTrail();

// Output that it was successfully set up
echo "EventLoggingProvider set up successfully. Got instance of " . get_class($auditTrailRecorder) . "\n";
