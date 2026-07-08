<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';
example_requires_pdo($pdo);

use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\AuthoritativeAudit\Command\RecordAuthoritativeAuditCommand;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;

/**
 * 03 - Authoritative Audit Record
 *
 * Show how to record a fail-closed authoritative audit event.
 * Note that AuthoritativeAudit is fail-closed, meaning it does NOT use a fallback logger.
 * If the write fails, an exception will be thrown.
 */

// We assume $pdo and $clock are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\EventLogging\Common\ClockInterface $clock

// We don't pass the logger to the provider for Authoritative Audit
$provider = EventLoggingProviderFactory::createDefault($pdo, $clock);
$authoritativeAudit = $provider->authoritativeAudit();

$command = new RecordAuthoritativeAuditCommand(
    action: 'update_permissions',
    targetType: 'user',
    targetId: 123,
    riskLevel: 'HIGH',
    actorType: 'admin',
    actorId: 1,
    payload: ['role' => 'superuser'],
    correlationId: 'abc-123'
);

echo "Attempting to record authoritative audit...\n";

try {
    $authoritativeAudit->recordCommand($command);
    echo "Recorded authoritative audit successfully.\n";
} catch (AuthoritativeAuditStorageException $e) {
    // Expected to fail if there's no real DB connection, because this domain is fail-closed
    echo "Failed to record authoritative audit: " . $e->getMessage() . "\n";
}
