<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';
example_requires_pdo($pdo);

use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;

/**
 * 09 - Admin Read Audit Trail
 *
 * Show how to query the audit trail for admin UI.
 */

// We assume $pdo is available from 00-bootstrap.php.
// @var \PDO $pdo

$repository = new AuditTrailQueryMysqlRepository($pdo);
$queryDTO = new AuditTrailQueryDTO(
    actorType: 'user',
    actorId: 42
);

// Note: If you run this without a real database, it will throw an exception.
echo "Attempting to query audit trail...\n";
try {
    $results = $repository->find($queryDTO);
    echo "Found " . count($results) . " results.\n";
    foreach ($results as $result) {
        echo "- Event: " . $result->eventKey . " at " . $result->occurredAt->format(\DateTimeInterface::ATOM) . "\n";
    }
} catch (\Throwable $e) {
    echo "Query failed (expected if DB is unreachable): " . $e->getMessage() . "\n";
}
