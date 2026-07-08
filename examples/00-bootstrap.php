<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Maatify\EventLogging\Common\SystemClock;
use Psr\Log\AbstractLogger;

/**
 * THIS IS A SKELETON BOOTSTRAP FILE.
 * It demonstrates how a host application provides the dependencies required by the package.
 *
 * It uses safe placeholder configurations and a dummy PSR-3 logger.
 * Do NOT put real credentials or DB endpoints in here.
 */

// 1. Setup a dummy or real PDO
// To actually test MySQL examples, provide a safe DB_DSN environment variable
$dsn = getenv('DB_DSN');
$pdo = null;
if ($dsn) {
    // Only connect if the host explicitly provides a DSN string via environment variable
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

function example_requires_pdo(?PDO $pdo): void
{
    if ($pdo === null) {
        echo "This example requires a configured PDO instance.\n";
        echo "Please provide a safe MySQL DSN via the DB_DSN environment variable to execute.\n";
        exit(0);
    }
}

// 2. Setup the clock
$clock = new SystemClock();

// 3. Setup a fallback logger (optional for fail-open domains)
$logger = new class extends AbstractLogger {
    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        echo sprintf("[%s] %s %s\n", strtoupper((string)$level), (string)$message, json_encode($context));
    }
};
