<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';
example_requires_pdo($pdo);

use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\AuditTrail\Command\RecordAuditTrailCommand;

/**
 * 04 - Audit Trail Record
 *
 * Show how to record an audit trail event.
 * Demonstrates both the primitive record() method and recordCommand().
 */

// We assume $pdo, $clock, and $logger are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\SharedCommon\Contracts\ClockInterface $clock
// @var \Psr\Log\LoggerInterface $logger

$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $logger);
$auditTrail = $provider->auditTrail();

echo "Recording via primitive method...\n";
// Using the primitive convenience method
$auditTrail->record(
    eventKey: 'user_login',
    actorType: 'user',
    actorId: 42,
    entityType: 'session',
    entityId: 123
);

echo "Recording via command method...\n";
// Using a Command object
$command = new RecordAuditTrailCommand(
    eventKey: 'user_logout',
    actorType: 'user',
    actorId: 42,
    entityType: 'session',
    entityId: 123,
    metadata: ['reason' => 'user_initiated']
);
$auditTrail->recordCommand($command);

echo "Recorded audit trail events.\n";
