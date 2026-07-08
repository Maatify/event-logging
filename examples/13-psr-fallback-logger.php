<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';

use Maatify\EventLogging\AuditTrail\Command\RecordAuditTrailCommand;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
use Maatify\EventLogging\Common\SystemClock;
use Psr\Log\AbstractLogger;

/**
 * 13 - PSR Fallback Logger
 *
 * Demonstrate fail-open behavior with a PSR-3 fallback logger.
 */

// 1. We create a DB connection we KNOW will fail (bad DSN).
try {
    $failingPdo = new \PDO('mysql:host=invalid_host;dbname=test', 'user', 'pass', [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
    ]);
} catch (\PDOException $e) {
    // If it fails immediately on construct, we'll just mock it.
    // For this example, let's use the memory sqlite which will fail because the table doesn't exist.
    $failingPdo = new \PDO('sqlite::memory:');
    $failingPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
}

// 2. We use a simple logger that echo's to the screen so we can see the fallback happen.
$logger = new class extends AbstractLogger {
    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        echo "FALLBACK LOGGER CAUGHT EVENT:\n";
        echo sprintf("[%s] %s %s\n", strtoupper((string)$level), (string)$message, json_encode($context));
    }
};

$repository = new AuditTrailLoggerMysqlRepository($failingPdo);
$clock = new SystemClock();

$recorder = new AuditTrailRecorder(
    logger: $repository,
    clock: $clock,
    fallbackLogger: $logger
);

$command = new RecordAuditTrailCommand(
    eventKey: 'user_login',
    actorType: 'user',
    actorId: 1,
    entityType: 'session',
    entityId: 1
);

echo "Attempting to record event with failing DB...\n";
// This should NOT throw an exception, it should be swallowed and sent to the fallback logger.
$recorder->recordCommand($command);
echo "Script finished without fatal exception.\n";
