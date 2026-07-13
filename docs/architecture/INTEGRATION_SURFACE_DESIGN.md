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
*   Optional framework-agnostic construction helpers (Factories and DI binding helpers)
*   Domain-scoped Admin Query contracts
*   Domain filters and trusted query construction
*   Domain-scoped reporting and dashboard summary contracts

**Host Application owns:**
*   PDO connection creation and configuration
*   Runtime configuration (environment variables)
*   Dependency Injection (DI) and container wiring
*   Controllers, routes, and middleware
*   Permissions, authorization, and actor resolution
*   Dashboard UI and presentation
*   Export generation
*   Host-specific orchestration and cross-system analytics
*   Localization
*   Actor/entity name resolution
*   Framework-specific service providers (e.g., Laravel ServiceProvider, Symfony Bundle)

## 2. EventLoggingManager Decision

**Decision: Expose a typed service map (`EventLoggingProvider`), but avoid any generic recording API.**

The package will NOT expose a generic `EventLoggingManager` that takes a `$domain` string and routes logs dynamically. Auto-routing generic APIs violate the isolation of logging domains.

Instead, if a central object is provided, it will strictly act as a service locator/accessor map providing strongly-typed access to individual domain recorders. For example:

```php
public function authoritativeAudit(): AuthoritativeAuditRecorder;
public function auditTrail(): AuditTrailRecorder;
// ...
```

This preserves domain-specific entry points and prevents the introduction of `GenericLogger`, `GenericDTO`, or `GenericRecorder`.

## 3. Bootstrap / DI

**Decision: The package may include optional, framework-agnostic `Bootstrap` helpers, but they are never mandatory runtime integration points.**

The core remains framework-agnostic: host applications still own PDO creation, clock selection, runtime configuration, and container setup. Hosts may wire the package manually through factories/providers, or they may import the optional pure-PHP binding helper when their DI container supports callable definitions.

The optional `EventLoggingBindings` helper exists only as a convenience map for DI wiring. It must not introduce Laravel, Slim, Symfony, host-application, or PHP-DI runtime behavior into the core package. It depends on host-provided `PDO` and `ClockInterface` entries, treats `LoggerInterface` as optional, and preserves the domain semantics documented for `EventLoggingProviderFactory`.

## 4. Public Contracts

The existing public services, repositories, and contracts defined in `EVENT_LOGGING_PACKAGE_REFERENCE.md` will remain public.

*   `Command\*`: Public input structures for logging.
*   `Contract\*`: Interfaces for recorders and writers, useful for testing or swapping infrastructure.
*   `Recorder\*`: The primary interaction point for the application to record logs.
*   `Infrastructure\Mysql\*`: The default PDO implementations.

A new optional interface for the Provider/Service Map may be introduced, but it will be framework-agnostic.

## 5. Architecture Constraints

This design adheres to all constraints:
*   **Framework-agnostic:** Optional DI binding helpers are pure PHP convenience maps only; no Slim/Laravel/Symfony dependencies or required container packages.
*   **Standalone boundaries:** Library logic is isolated from host logic.
*   **API Stability:** Existing contracts in `EVENT_LOGGING_PACKAGE_REFERENCE.md` are unaffected.
*   **Prohibited Patterns Avoided:** No `GenericLogger`, generic DTOs, generic recorders, generic tables, or auto-routing.
*   **Module Building Standard:** Aligns with standard namespace boundaries and public contract requirements, utilizing documented exceptions for DI bindings and Admin/Customer splits.
