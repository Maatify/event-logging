<?php

/**
 * Example 14: Using Optional DI Bindings
 *
 * This example illustrates how a host application can use the optional
 * `EventLoggingBindings::definitions()` helper to configure a DI container.
 *
 * Requirements:
 * - The host application must use a DI container capable of taking an array of
 *   callable definitions (e.g., PHP-DI).
 * - The host container must provide bindings for:
 *   - PDO::class
 *   - Maatify\SharedCommon\Contracts\ClockInterface::class
 *   - (Optional) Psr\Log\LoggerInterface::class
 *
 * Note: This package does NOT depend on PHP-DI or any specific framework. The bindings
 * are pure PHP callables.
 *
 * The optional PSR-3 LoggerInterface is used ONLY as a fallback for fail-open domains.
 * The `AuthoritativeAudit` domain is fail-closed and will never use the fallback logger.
 *
 * @see \Maatify\EventLogging\Bootstrap\EventLoggingBindings
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceRecorder;
use Maatify\EventLogging\Bootstrap\EventLoggingBindings;
use Maatify\EventLogging\Common\SystemClock;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Note: The following is a pseudo-container setup for illustrative purposes.
 * In a real application, you would use a concrete container like PHP-DI's ContainerBuilder.
 */
class DummyContainer implements ContainerInterface {
    private array $definitions = [];
    private array $instances = [];

    public function __construct(array $definitions) {
        $this->definitions = $definitions;
    }

    public function get(string $id) {
        if (!isset($this->instances[$id])) {
            if (!isset($this->definitions[$id])) {
                throw new \Exception("No definition for $id");
            }
            $callable = $this->definitions[$id];
            $this->instances[$id] = $callable($this);
        }
        return $this->instances[$id];
    }

    public function has(string $id): bool {
        return isset($this->definitions[$id]);
    }
}

// 1. Define the host application's required dependencies
$hostDependencies = [
    // Database connection using a safe dummy DSN
    PDO::class => function () {
        return new PDO(
            getenv('EVENT_LOGGING_TEST_MYSQL_DSN') ?: 'mysql:host=127.0.0.1;dbname=dummy_db',
            'dummy_user',
            'dummy_pass',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    },

    // Clock implementation for deterministic timestamps
    ClockInterface::class => function () {
        return new SystemClock();
    },

    // Optional PSR-3 Logger for fail-open domains
    LoggerInterface::class => function () {
        return new NullLogger(); // Or Monolog, etc.
    },
];

// 2. Merge the host dependencies with the EventLogging package bindings
$allDefinitions = array_merge(
    $hostDependencies,
    EventLoggingBindings::definitions()
);

// 3. Build the container
$container = new DummyContainer($allDefinitions);

// 4. Retrieve and use a domain recorder
// Example: AuthoritativeAudit (Fail-Closed)
try {
    /** @var AuthoritativeAuditRecorder $authoritativeAudit */
    $authoritativeAudit = $container->get(AuthoritativeAuditRecorder::class);

    // This will throw an exception if the PDO connection fails,
    // because AuthoritativeAudit does NOT use the fallback logger.
    echo "AuthoritativeAuditRecorder retrieved successfully.\n";
} catch (\Throwable $e) {
    echo "AuthoritativeAudit setup failed as expected if PDO is invalid: " . $e->getMessage() . "\n";
}

// Example: BehaviorTrace (Fail-Open)
try {
    /** @var BehaviorTraceRecorder $behaviorTrace */
    $behaviorTrace = $container->get(BehaviorTraceRecorder::class);

    // If PDO fails during recording, BehaviorTrace will catch the exception
    // and route the error to the LoggerInterface we provided above.
    echo "BehaviorTraceRecorder retrieved successfully.\n";
} catch (\Throwable $e) {
    echo "BehaviorTrace setup failed: " . $e->getMessage() . "\n";
}
