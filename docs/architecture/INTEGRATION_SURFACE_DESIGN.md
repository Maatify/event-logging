# Integration Surface Design

## 1. Library Responsibility vs Host Application Responsibility

The boundary between the `maatify/event-logging` library and host applications is strictly defined to preserve the package's framework-agnostic, standalone nature.

**Library owns:**
*   Domain commands and input contracts (`Command\*`)
*   Domain data transfer objects (`DTO\*`)
*   Domain policies and validation rules
*   Domain recorders (`Recorder\*`)
*   Persistence contracts (`Contract\*`)
*   Domain repositories (`Infrastructure\Mysql\*`)
*   Optional framework-agnostic construction helpers (Factories)

**Host Application owns:**
*   PDO connection creation and configuration
*   Runtime configuration (environment variables)
*   Dependency Injection (DI) and container wiring
*   Controllers, routes, and middleware
*   Permissions, authorization, and actor resolution
*   Admin UI, dashboards, and complex analytics
*   Export generation
*   Framework-specific service providers (e.g., Laravel ServiceProvider, Symfony Bundle)

## 2. EventLoggingManager Decision

**Decision: Expose a typed service map (`EventLoggerProvider` or `LoggingManager`), but avoid any generic recording API.**

The package will NOT expose a generic `EventLoggingManager` that takes a `$domain` string and routes logs dynamically. Auto-routing generic APIs violate the isolation of logging domains.

Instead, if a central object is provided, it will strictly act as a service locator/accessor map providing strongly-typed access to individual domain recorders. For example:

```php
public function authoritativeAudit(): AuthoritativeAuditRecorder;
public function auditTrail(): AuditTrailRecorder;
// ...
```

This preserves domain-specific entry points and prevents the introduction of `GenericLogger`, `GenericDTO`, or `GenericRecorder`.

## 3. Bootstrap / DI

**Decision: The package will NOT include a standard `Bootstrap/{ModuleName}Bindings.php`.**

According to the `EVENT_LOGGING_MODULE_REFERENCE.md` exceptions, this package intentionally avoids providing project-specific bindings to maintain framework-agnosticism.

Host applications should wire dependencies manually or via their own DI container by choosing the required domain contracts, policies, `PDO` connection, and optional PSR-3 logger. The package will provide pure PHP Factory classes (see `FACTORY_AND_PROVIDER_DESIGN.md`) to ease this manual wiring, but will not assume any specific DI container (e.g., PHP-DI, Laravel).

## 4. Public Contracts

The existing public services, repositories, and contracts defined in `PUBLIC_API.md` will remain public.

*   `Command\*`: Public input structures for logging.
*   `Contract\*`: Interfaces for recorders and writers, useful for testing or swapping infrastructure.
*   `Recorder\*`: The primary interaction point for the application to record logs.
*   `Infrastructure\Mysql\*`: The default PDO implementations.

A new optional interface for the Provider/Service Map may be introduced, but it will be framework-agnostic.

## 5. Architecture Constraints

This design adheres to all constraints:
*   **Framework-agnostic:** No DI container bindings, no Slim/Laravel/Symfony dependencies.
*   **Standalone boundaries:** Library logic is isolated from host logic.
*   **API Stability:** Existing contracts in `PUBLIC_API.md` are unaffected.
*   **Prohibited Patterns Avoided:** No `GenericLogger`, generic DTOs, generic recorders, generic tables, or auto-routing.
*   **Module Building Standard:** Aligns with standard namespace boundaries and public contract requirements, utilizing documented exceptions for DI bindings and Admin/Customer splits.
