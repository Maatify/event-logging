# Factory Usage

The `maatify/event-logging` library provides optional, framework-agnostic factories to help wire the various logging domains in your application without enforcing any specific dependency injection container.

## Dependencies Provided by the Host

To instantiate the provider or individual factories, the host application must construct and supply the following dependencies:

1. **`PDO`**: A standard configured PDO instance connected to your database.
2. **`ClockInterface`**: An instance implementing `Maatify\SharedCommon\Contracts\ClockInterface` (e.g., `Maatify\EventLogging\Common\SystemClock`).
3. **`LoggerInterface` (Optional)**: A PSR-3 compatible logger to provide fail-open fallback behavior for specific domains.

## Creating the Default Provider

The simplest way to use the library is via the `EventLoggingProviderFactory`. It takes your dependencies and returns an `EventLoggingProvider` that exposes typed accessors for each domain.

```php
use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\Common\SystemClock;

// 1. Host provides a PDO instance
$pdo = new \PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');

// 2. Host provides a clock instance
$clock = new SystemClock();

// 3. Host may provide a PSR-3 logger (e.g. Monolog) for fail-open domains
$psrLogger = new \Monolog\Logger('event-logging-fallback');
// ... configure Monolog ...

// Create the provider
$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $psrLogger);
```

## Typed Provider Accessors

The `EventLoggingProvider` provides explicit, typed accessors to retrieve the recorders for each domain. **There is no auto-routing by string and no generic `log()` API.**

```php
// Retrieve domain-specific recorders
$authoritativeAudit = $provider->authoritativeAudit();
$auditTrail = $provider->auditTrail();
$securitySignals = $provider->securitySignals();
$behaviorTrace = $provider->behaviorTrace();
$diagnosticsTelemetry = $provider->diagnosticsTelemetry();
$deliveryOperations = $provider->deliveryOperations();
```

Each method returns the precise interface for that domain's recorder, enforcing isolated recording APIs.

## Domain-Specific Factories

If you do not want to use the unified provider, you can use the individual domain factories located under `Maatify\EventLogging\Factory\`.

```php
use Maatify\EventLogging\Factory\AuditTrailFactory;

$auditTrailRecorder = AuditTrailFactory::create($pdo, $clock, $psrLogger);
```

### Important: AuthoritativeAudit Fail-Closed Semantics

The `AuthoritativeAudit` domain acts as the governance and security posture log. It uses **fail-closed** semantics, meaning if it fails to record an event to the database, it must throw an exception rather than silently succeeding or falling back to a file.

As such, the factory for `AuthoritativeAudit` **does not receive the optional PSR-3 fallback logger**.

```php
use Maatify\EventLogging\Factory\AuthoritativeAuditFactory;

// Notice there is no $psrLogger parameter here:
$authoritativeAuditRecorder = AuthoritativeAuditFactory::create($pdo, $clock);
```
