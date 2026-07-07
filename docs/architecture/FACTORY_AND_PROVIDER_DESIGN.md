# Factory and Provider Design

## 1. Overview

To simplify the integration of the `maatify/event-logging` package without dictating a specific Dependency Injection container, the package will provide optional, framework-agnostic **Factories** and a **Provider** (Service Map).

These components are designed to construct domain-specific loggers/recorders with explicit dependencies, preserving the isolated boundaries of each logging domain.

## 2. Factory Design

**Decision: Optional, domain-specific factories will be provided.**

Factories will construct each domain logger/recorder. They require explicit dependencies.

### Construction API / Shape

Each factory will typically require:
1.  `PDO`: The database connection.
2.  `ClockInterface`: (e.g., `SystemClock`) For timestamp generation.
3.  `?LoggerInterface`: An optional PSR-3 logger. For fail-open domains, this serves as a fallback logger. For the fail-closed AuthoritativeAudit domain, a PSR-3 logger is not a fallback that alters failure semantics; AuthoritativeAudit remains strictly fail-closed.
4.  `?DomainPolicy`: Optional domain-specific policies where applicable.

Example Factory signature (conceptual - showing fail-open vs fail-closed):

```php
namespace Maatify\EventLogging\Factory;

use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
// ... imports

final class AuthoritativeAuditFactory
{
    // AuthoritativeAudit is fail-closed. No PSR-3 fallback logger is accepted
    // unless explicitly documented for diagnostic-only usage.
    public static function create(
        \PDO $pdo,
        ClockInterface $clock
    ): AuthoritativeAuditRecorder {
        $writer = static::createWriter($pdo);
        return new AuthoritativeAuditRecorder($writer, $clock);
    }

    // ... public static function createWriter(\PDO $pdo) ...
}

final class AuditTrailFactory
{
    // AuditTrail is fail-open and accepts an optional PSR-3 fallback logger.
    public static function create(
        \PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null
    ): AuditTrailRecorder {
        $writer = static::createWriter($pdo);
        return new AuditTrailRecorder($writer, $clock, $psrLogger);
    }
}
```

A factory class will be created for each of the six canonical domains:
*   `AuthoritativeAuditFactory`
*   `AuditTrailFactory`
*   `SecuritySignalsFactory`
*   `BehaviorTraceFactory`
*   `DiagnosticsTelemetryFactory`
*   `DeliveryOperationsFactory`

These factories MUST NOT hide domain boundaries (e.g., they will not return a unified `LoggerInterface` but rather the specific `DomainRecorder`).

## 3. Provider / Bindings

**Decision: An optional, framework-agnostic Provider / Service Map will be included.**

To allow host applications to inject a single object that provides access to all event logging capabilities, an `EventLoggingProvider` will be provided.

### Characteristics:
*   **Framework-Agnostic:** It is a pure PHP class. It does not implement any framework-specific service provider interface (e.g., Illuminate\Support\ServiceProvider).
*   **No Generic Routing:** It will not have a `log(string $domain, array $data)` method.
*   **Typed Accessors:** It will expose explicit getter methods for each domain recorder.

Example Provider signature (conceptual):

```php
namespace Maatify\EventLogging\Provider;

use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
// ... imports

final class EventLoggingProvider
{
    public function __construct(
        private readonly AuthoritativeAuditRecorder $authoritativeAudit,
        private readonly AuditTrailRecorder $auditTrail,
        private readonly SecuritySignalsRecorder $securitySignals,
        private readonly BehaviorTraceRecorder $behaviorTrace,
        private readonly DiagnosticsTelemetryRecorder $diagnosticsTelemetry,
        private readonly DeliveryOperationsRecorder $deliveryOperations
    ) {}

    public function authoritativeAudit(): AuthoritativeAuditRecorder
    {
        return $this->authoritativeAudit;
    }

    // ... getters for other domains
}
```

### Factory for the Provider

A central factory may be provided to construct the `EventLoggingProvider` rapidly if default policies are acceptable:

```php
final class EventLoggingProviderFactory
{
    public static function createDefault(
        \PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null
    ): EventLoggingProvider {
        return new EventLoggingProvider(
            AuthoritativeAuditFactory::create($pdo, $clock), // Fail-closed, no fallback logger
            AuditTrailFactory::create($pdo, $clock, $psrLogger),
            // ...
        );
    }
}
```

## 4. Alignment with Rules

*   **No Slim/PHP-DI bindings:** The package relies purely on constructor injection. Host frameworks will map their DI definitions to these factories manually.
*   **No Host Assumptions:** The factories assume nothing about the host environment other than the availability of a `PDO` instance and standard interfaces.
