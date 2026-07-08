<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';

use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;

/**
 * 10 - Admin Read Authoritative Audit
 *
 * Show how to query authoritative audit logs.
 */

// We assume $pdo is available from 00-bootstrap.php.
// @var \PDO $pdo

$repository = new AuthoritativeAuditQueryMysqlRepository($pdo);
$queryDTO = new AuthoritativeAuditQueryDTO(
    actorType: 'admin',
    actorId: 1
);

echo "Attempting to query authoritative audit...\n";
try {
    $results = $repository->find($queryDTO);
    echo "Found " . count($results) . " results.\n";
    foreach ($results as $result) {
        echo "- Action: " . $result->action . " at " . $result->occurredAt->format(\DateTimeInterface::ATOM) . "\n";
    }
} catch (\Throwable $e) {
    echo "Query failed (expected if using dummy SQLite PDO): " . $e->getMessage() . "\n";
}
