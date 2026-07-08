<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';
example_requires_pdo($pdo);

use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\SecuritySignals\Command\RecordSecuritySignalCommand;

/**
 * 05 - Security Signal Record
 *
 * Show how to record a security signal.
 * Make sure to sanitize sensitive data before passing it.
 */

// We assume $pdo, $clock, and $logger are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\SharedCommon\Contracts\ClockInterface $clock
// @var \Psr\Log\LoggerInterface $logger

$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $logger);
$securitySignals = $provider->securitySignals();

$command = new RecordSecuritySignalCommand(
    signalType: 'failed_login',
    severity: 'HIGH',
    actorType: 'user',
    actorId: 42,
    metadata: ['ip_blacklisted' => true]
);

$securitySignals->recordCommand($command);

echo "Recorded security signal event.\n";
