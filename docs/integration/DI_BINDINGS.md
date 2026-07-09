# Optional DI Bindings

The `maatify/event-logging` package provides an optional convenience helper, `EventLoggingBindings::definitions()`, designed to simplify wiring the package domains within host applications that utilize Dependency Injection (DI) containers.

## ⚠️ Key Architectural Guarantees

Before using this feature, please understand its boundaries:
- **Pure PHP**: The helper returns an array of standard PHP closures. It does **not** rely on `php-di/php-di` (which is only a `composer suggest` item) or any specific framework.
- **No Mandatory Dependencies**: You are absolutely free to use [Manual Wiring](MANUAL_WIRING.md) or [Factory Usage](FACTORY_USAGE.md). The bindings are an optional convenience.
- **Strictly Isolated**: The helper does not provide bindings for controllers, routes, UI components, permissions, generic logging APIs, or domain-string routed loggers.

## Host Application Requirements

If you choose to use `EventLoggingBindings::definitions()`, your host container must be configured to provide the following dependencies:

1. **`PDO::class`**: A configured PDO connection (compatible with the `utf8mb4_unicode_ci` schema).
2. **`ClockInterface::class`**: An implementation of `Maatify\SharedCommon\Contracts\ClockInterface` (e.g., `Maatify\EventLogging\Common\SystemClock`).
3. **`LoggerInterface::class` (Optional)**: A PSR-3 `LoggerInterface` implementation used as a fallback.

### Important Note on Fallback Logging
The optional PSR-3 `LoggerInterface` is **only** used for fail-open domains (e.g., `BehaviorTrace`, `SecuritySignals`).
The `AuthoritativeAudit` domain explicitly rejects the fallback logger to maintain its strict **fail-closed** guarantee. Storage failures during authoritative auditing will surface as system exceptions rather than silently degrading.

## Usage Example

Import the bindings and merge them into your container definitions:

```php
use Maatify\EventLogging\Bootstrap\EventLoggingBindings;
use Maatify\EventLogging\Common\SystemClock;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

// 1. Define the host requirements
$hostDefinitions = [
    PDO::class => function () {
        return new PDO('mysql:host=127.0.0.1;dbname=app_db', 'user', 'pass');
    },
    ClockInterface::class => function () {
        return new SystemClock();
    },
    LoggerInterface::class => function () {
        return new NullLogger(); // Optional
    },
];

// 2. Merge with package bindings
$definitions = array_merge(
    $hostDefinitions,
    EventLoggingBindings::definitions()
);

// 3. Register definitions with your container (e.g., PHP-DI)
$containerBuilder = new \DI\ContainerBuilder();
$containerBuilder->addDefinitions($definitions);
$container = $containerBuilder->build();

// 4. Resolve a domain recorder directly
$auditTrail = $container->get(\Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder::class);
```

For a plain PHP illustrative skeleton, see the [`14-di-bindings.php`](../../examples/14-di-bindings.php) example file.
