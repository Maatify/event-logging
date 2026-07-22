# Event Logging Package Reference

`maatify/event-logging` is the canonical framework-agnostic Composer package for Maatify event logging. This document is the single root package-reference source of truth for the stable package contract, public runtime API, infrastructure adapters, schema ownership, and package-specific standards decisions.

## 1. Package identity and stable contract

- Composer package: `maatify/event-logging`.
- PHP namespace root: `Maatify\EventLogging\`.
- Package type: standalone, framework-agnostic Composer library.
- Persistence model: host-provided `PDO` with domain-owned MySQL repositories and schemas.
- Runtime model: host applications provide identifiers, context, `PDO`, clock, and optional fallback logger; the package does not manage host runtime lifecycles.
- Public contract model: domain-specific commands, contracts, DTOs, enums, exceptions, recorders, factories, provider services, optional binding definitions, and MySQL composition adapters.
- Boundary model: each logging domain owns its own write contract, read contract, schema, storage exception, policies, DTOs, and recorder behavior.

The final repository has exactly one canonical root Package Reference: this file, `EVENT_LOGGING_PACKAGE_REFERENCE.md`.

## 2. External runtime dependencies

The package intentionally uses explicit runtime dependencies rather than hiding host or framework assumptions:

- PHP `^8.2`.
- PHP extensions: `ext-json`, `ext-pdo`.
- `maatify/exceptions`.
- `maatify/persistence` for AuditTrail, BehaviorTrace, SecuritySignals, and AuthoritativeAudit Admin Query offset pagination mechanics.
- `maatify/shared-common` for shared contracts such as `ClockInterface`.
- `psr/log` for optional fail-open fallback logging.
- `ramsey/uuid` for UUID generation; the package does not provide an internal UUID fallback generator.

## 3. Complete public Runtime API inventory

The package exposes `Maatify\EventLogging\` via PSR-4 autoloading. The public runtime surface consists of the following namespaces and classes.

### Domain namespace groups

- `Maatify\EventLogging\AuthoritativeAudit\Command\*`
- `Maatify\EventLogging\AuthoritativeAudit\Contract\*`
- `Maatify\EventLogging\AuthoritativeAudit\DTO\*`
- `Maatify\EventLogging\AuthoritativeAudit\Enum\*`
- `Maatify\EventLogging\AuthoritativeAudit\Exception\*`
- `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\*`
- `Maatify\EventLogging\AuthoritativeAudit\Recorder\*`
- `Maatify\EventLogging\AuthoritativeAudit\Service\*`
- `Maatify\EventLogging\AuditTrail\Command\*`
- `Maatify\EventLogging\AuditTrail\Contract\*`
- `Maatify\EventLogging\AuditTrail\DTO\*`
- `Maatify\EventLogging\AuditTrail\Enum\*`
- `Maatify\EventLogging\AuditTrail\Exception\*`
- `Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\*`
- `Maatify\EventLogging\AuditTrail\Recorder\*`
- `Maatify\EventLogging\SecuritySignals\Command\*`
- `Maatify\EventLogging\SecuritySignals\Contract\*`
- `Maatify\EventLogging\SecuritySignals\DTO\*`
- `Maatify\EventLogging\SecuritySignals\Enum\*`
- `Maatify\EventLogging\SecuritySignals\Exception\*`
- `Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\*`
- `Maatify\EventLogging\SecuritySignals\Recorder\*`
- `Maatify\EventLogging\BehaviorTrace\Command\*`
- `Maatify\EventLogging\BehaviorTrace\Contract\*`
- `Maatify\EventLogging\BehaviorTrace\DTO\*`
- `Maatify\EventLogging\BehaviorTrace\Enum\*`
- `Maatify\EventLogging\BehaviorTrace\Exception\*`
- `Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\*`
- `Maatify\EventLogging\BehaviorTrace\Recorder\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Command\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Contract\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\DTO\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Enum\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Exception\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Recorder\*`
- `Maatify\EventLogging\DeliveryOperations\Command\*`
- `Maatify\EventLogging\DeliveryOperations\Contract\*`
- `Maatify\EventLogging\DeliveryOperations\DTO\*`
- `Maatify\EventLogging\DeliveryOperations\Enum\*`
- `Maatify\EventLogging\DeliveryOperations\Exception\*`
- `Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\*`
- `Maatify\EventLogging\DeliveryOperations\Recorder\*`

### Shared, factory, provider, and bootstrap classes

- `Maatify\EventLogging\Common\SystemClock`
- `Maatify\EventLogging\Common\UrlSanitizer`
- `Maatify\EventLogging\Common\MetadataSanitizer`
- `Maatify\EventLogging\Factory\AuthoritativeAuditFactory`
- `Maatify\EventLogging\Factory\AuditTrailFactory`
- `Maatify\EventLogging\Factory\SecuritySignalsFactory`
- `Maatify\EventLogging\Factory\BehaviorTraceFactory`
- `Maatify\EventLogging\Factory\DiagnosticsTelemetryFactory`
- `Maatify\EventLogging\Factory\DeliveryOperationsFactory`
- `Maatify\EventLogging\Provider\EventLoggingProvider`
- `Maatify\EventLogging\Provider\EventLoggingProviderFactory`
- `Maatify\EventLogging\Bootstrap\EventLoggingBindings`
- `Maatify\EventLogging\Exception\EventLoggingExceptionInterface`

No `App\`, project DI/container, project helper, route, middleware, controller, permission, or host-application-specific configuration API is part of the exported package surface.

## 4. Six logging domains

1. **AuthoritativeAudit** — authoritative governance and security posture events that must be durably queued or fail closed.
2. **AuditTrail** — read-side and visibility events such as views, exports, navigation, and audit queries.
3. **SecuritySignals** — authentication, authorization, abuse, and anomaly signals.
4. **BehaviorTrace** — mutation-oriented operational behavior trace events.
5. **DiagnosticsTelemetry** — technical observability, diagnostics, and performance telemetry.
6. **DeliveryOperations** — asynchronous jobs, notifications, webhooks, provider delivery attempts, and lifecycle transitions.

Each domain is isolated. Cross-domain helpers are limited to neutral utilities and must not become cross-domain writers or repositories.

## 5. `Common` public primitives

`Common` contains framework-neutral primitives only:

- `SystemClock`: package clock implementation for hosts that want a default `ClockInterface` implementation.
- `UrlSanitizer`: URL normalization/sanitization helper for logging-safe values.
- `MetadataSanitizer`: metadata sanitization helper for safe structural metadata.

`Common` is not a shared logging domain and does not own logging policy or persistence.

## 6. `Factory` public surface

The factory layer provides explicit domain construction helpers:

- `AuthoritativeAuditFactory`
- `AuditTrailFactory`
- `SecuritySignalsFactory`
- `BehaviorTraceFactory`
- `DiagnosticsTelemetryFactory`
- `DeliveryOperationsFactory`

Factories compose domain recorders with host-provided dependencies, domain policies, and infrastructure repositories. They are package-level convenience APIs, not a framework container.

## 7. `Provider` public surface

- `EventLoggingProvider` exposes typed accessors for the six domain recorders.
- `EventLoggingProviderFactory` builds the default provider graph from host-provided `PDO`, `ClockInterface`, and optional `LoggerInterface`.

The provider is a convenience composition boundary. Application/business code should still depend on explicit domain contracts when possible.

## 8. `Bootstrap\EventLoggingBindings`

`Maatify\EventLogging\Bootstrap\EventLoggingBindings` is an existing public optional bootstrap helper. It:

- provides optional plain-PHP callable definitions through `definitions()`;
- is framework-agnostic and does not depend on PHP-DI, Symfony, Laravel, or any mandatory container package;
- requires the host container/composition root to provide `PDO` and `Maatify\SharedCommon\Contracts\ClockInterface`;
- optionally consumes `Psr\Log\LoggerInterface` for fail-open fallback logging;
- binds `EventLoggingProvider`, all six recorders, and the six primitive query interfaces to MySQL query repositories;
- expects only a container-like object with `get(string $id)` and optionally `has(string $id)`; and
- does not introduce a mandatory container dependency or framework runtime wiring.

## 9. Commands, write DTOs, view DTOs, and query DTOs

Commands are the public mutation input contracts for recording events. Each domain has its own command object under `Maatify\EventLogging\<Domain>\Command`; commands are `final readonly` and perform boundary validation such as required strings, non-negative attempt numbers, and positive host-provided identifiers when present. Hosts may construct commands and pass them to `recordCommand()` or call a recorder's primitive `record()` convenience method.

Write DTOs are domain-specific recorder-to-writer transfer objects. They are built by recorders after command validation, policy normalization, metadata handling, timestamp assignment, and event-id generation. Normal host recording should not require manually constructing write DTOs.

View DTOs are read/output models returned by primitive query interfaces and read repositories. Query DTOs are immutable primitive filter carriers for read-side operations. Cursor/page DTOs model cursor pagination where present.

## 10. Recorder, writer, and repository responsibilities

Recorders own public recording behavior. They validate command input, apply domain policies, generate event identifiers, normalize metadata, assign timestamps, construct write DTOs, call domain writers/repositories, and enforce each domain's fail-open or fail-closed boundary.

Writers and repositories persist already-structured DTOs only. They do not apply policy, generate event ids, normalize actor/severity values, or decide fail-open/fail-closed behavior. Storage failures are wrapped in domain-specific storage exceptions.

Query repositories read stored rows and hydrate view DTOs. They are primitive read adapters, not generic readers, report builders, controllers, or UI-grid backends.

## 11. Primitive cursor-based read/query contracts

The package exposes only domain-specific primitive query contracts, DTOs, and MySQL repositories for host-owned admin viewing foundations. It does not provide generic readers, admin controllers, routes, middleware, permissions, exports, analytics, CRUD APIs, joins, aggregations, or arbitrary filtering.

- Authoritative audit: `AuthoritativeAuditQueryInterface`, `AuthoritativeAuditQueryDTO`, `AuthoritativeAuditViewDTO`, and `AuthoritativeAuditQueryMysqlRepository` query `maa_event_logging_authoritative_audit_log` with actor, target, action, correlation, date-range, cursor, and limit filters. There is intentionally no `requestId` filter because the authoritative audit log table does not store `request_id`.
- Audit trail: `AuditTrailQueryInterface`, `AuditTrailQueryDTO`, `AuditTrailViewDTO`, and `AuditTrailQueryMysqlRepository` support actor, event key, entity, subject, request, correlation, date-range, cursor, and limit filters.
- Security signals: `SecuritySignalsQueryInterface`, `SecuritySignalsQueryDTO`, `SecuritySignalsViewDTO`, and `SecuritySignalsQueryMysqlRepository` support actor, signal type, severity, request, correlation, date-range, cursor, and limit filters.
- Behavior trace: `BehaviorTraceQueryInterface::find(BehaviorTraceQueryDTO $query)`, `BehaviorTraceQueryDTO`, `BehaviorTraceEventDTO`, and `BehaviorTraceQueryMysqlRepository` support actor, entity, action, request, correlation, date-range, cursor, and limit filters. The legacy cursor `read(?BehaviorTraceCursorDTO $cursor, int $limit = 100)` method remains for backward compatibility.
- Diagnostics telemetry: `DiagnosticsTelemetryQueryInterface::find(DiagnosticsTelemetryQueryDTO $query)`, `DiagnosticsTelemetryQueryDTO`, `DiagnosticsTelemetryEventDTO`, and `DiagnosticsTelemetryQueryMysqlRepository` support actor, event key, severity, request, correlation, date-range, cursor, and limit filters. The legacy cursor `read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100)` method remains for backward compatibility.
- Delivery operations: `DeliveryOperationsQueryInterface`, `DeliveryOperationsQueryDTO`, `DeliveryOperationsViewDTO`, and `DeliveryOperationsQueryMysqlRepository` support actor, target, channel, operation type, status, request, correlation, date-range, cursor, and limit filters.

All primitive query repositories order results by `occurred_at DESC, id DESC`, apply descending cursor pagination with `cursorOccurredAt` and `cursorId`, safely decode JSON payload/metadata fields to arrays, return `null` for corrupt JSON, wrap read/storage failures in the domain-specific storage exception, and provide fail-safe hydration by gracefully handling invalid persisted enum-like values through sanitizing or fallback behavior rather than throwing from hydration.

The primitive read side is designed for archiving, sequential processing, export jobs, and migration jobs.

## 12. Admin Query APIs

AuthoritativeAudit, AuditTrail, BehaviorTrace, SecuritySignals, and DiagnosticsTelemetry additionally expose separate Admin Query APIs for host-owned administrative screens that need deterministic offset pagination. These APIs are additive and do not replace primitive cursor-based query interfaces.

### AuthoritativeAudit

Public contract:

```php
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditAdminQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;

$query = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

$page = $query->paginate(new AuthoritativeAuditAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    action: 'role.assign',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

- **Filters:** `eventId`, `actorType`, `actorId`, `targetType`, `targetId`, `action`, `correlationId`, `after`, `before`.
- **Validation Exceptions:** `AuthoritativeAuditAdminQueryInvalidArgumentException`
- **Execution Exceptions:** `AuthoritativeAuditAdminQueryExecutionException`
- **Storage Exceptions:** `AuthoritativeAuditStorageException`

### AuditTrail

Public contract:

```php
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailAdminQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailAdminQueryMysqlRepository;

$query = new AuditTrailAdminQueryMysqlRepository($pdo);

$page = $query->paginate(new AuditTrailAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    eventKey: 'customer.view',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

Supported filters are `actorType`, `actorId`, `eventKey`, `entityType`, `entityId`, `subjectType`, `subjectId`, `requestId`, `correlationId`, `after`, and `before`. Type-only filters are valid. An ID without its corresponding type is rejected. Empty strings are normalized to `null`, IDs must be positive, equal date boundaries are valid, and date filters are inclusive.

The result DTO serializes as:

```text
items, page, perPage, total, filtered, totalPages, hasNext, hasPrevious, sortBy, sortDirection
```

Caller-selectable sorting is limited to `occurred_at`; `id` is used only as the internal deterministic tie-breaker. Pagination normalization, clamping, offset calculation, count execution, and ordering mechanics are delegated to `maatify/persistence`. The public EventLogging API does not expose persistence package classes.

Admin Query exception boundaries:

- `AuditTrailAdminQueryInvalidArgumentException` for invalid request filters and ranges.
- `AuditTrailAdminQueryExecutionException` for invalid pagination configuration or descriptor construction.
- `AuditTrailStorageException` for PDO and pagination execution failures, using the existing `Failed to query audit trail: ...` message pattern.

### BehaviorTrace

Public contract:

```php
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceAdminQueryInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceAdminQueryMysqlRepository;

$query = new BehaviorTraceAdminQueryMysqlRepository($pdo);

$page = $query->paginate(new BehaviorTraceAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    action: 'user.update',
    entityType: 'user',
    entityId: 456,
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

Supported filters are `actorType`, `actorId`, `action`, `entityType`, `entityId`, `requestId`, `correlationId`, `after`, and `before`. Type-only filters are valid. An ID without its corresponding type is rejected. Empty strings are normalized to `null`, IDs must be positive, equal date boundaries are valid, and date filters are inclusive.

The result DTO serializes as:

```text
items, page, perPage, total, filtered, totalPages, hasNext, hasPrevious, sortBy, sortDirection
```

Caller-selectable sorting is limited to `occurred_at`; `id` is used only as the internal deterministic tie-breaker. Pagination normalization, clamping, offset calculation, count execution, and ordering mechanics are delegated to `maatify/persistence`. The public EventLogging API does not expose persistence package classes.

BehaviorTrace Admin Query exception boundaries:

- `BehaviorTraceAdminQueryInvalidArgumentException` for invalid request filters and ranges.
- `BehaviorTraceAdminQueryExecutionException` for invalid pagination configuration or descriptor construction.
- `BehaviorTraceStorageException` for PDO and pagination execution failures, using the existing `Failed to query BehaviorTrace records: ...` message pattern.

### SecuritySignals

Public contract:

```php
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsAdminQueryInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsAdminQueryMysqlRepository;

$query = new SecuritySignalsAdminQueryMysqlRepository($pdo);

$page = $query->paginate(new SecuritySignalsAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    signalType: 'login_failed',
    severity: 'HIGH',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

Supported filters are `actorType`, `actorId`, `signalType`, `severity`, `requestId`, `correlationId`, `after`, and `before`. `actorType` and `actorId` are independent filters: type-only, ID-only, both, and neither are all valid. Empty strings are normalized to `null`, IDs must be positive, equal date boundaries are valid, and date filters are inclusive.

The result DTO serializes as:

```text
items, page, perPage, total, filtered, totalPages, hasNext, hasPrevious, sortBy, sortDirection
```

Caller-selectable sorting is limited to `occurred_at`; `id` is used only as the internal deterministic tie-breaker. Pagination normalization, clamping, offset calculation, count execution, and ordering mechanics are delegated to `maatify/persistence`. The public EventLogging API does not expose persistence package classes.

SecuritySignals Admin Query exception boundaries:

- `SecuritySignalsAdminQueryInvalidArgumentException` for invalid request filters and ranges.
- `SecuritySignalsAdminQueryExecutionException` for invalid pagination configuration or descriptor construction.
- `SecuritySignalsStorageException` for PDO and pagination execution failures, using the existing `Failed to query SecuritySignals records: ...` message pattern.

The package does not provide HTTP controllers, routes, authorization, middleware, UI, exports, localization, dashboards, free-text search, metadata search, joins, caching, or approximate counts for Admin Query. Hosts own those concerns.

### DiagnosticsTelemetry

Public contract:

```php
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryAdminQueryInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryAdminQueryMysqlRepository;

$query = new DiagnosticsTelemetryAdminQueryMysqlRepository($pdo);

$page = $query->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO(
    actorType: 'sys',
    actorId: 42,
    eventKey: 'http.request',
    severity: 'INFO',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

Supported filters are `actorType`, `actorId`, `eventKey`, `severity`, `requestId`, `correlationId`, `after`, and `before`. `actorType` and `actorId` are independent filters: type-only, ID-only, both, and neither are all valid. Empty strings are normalized to `null`, IDs must be positive, equal date boundaries are valid, and date filters are inclusive.

The result DTO serializes as:

```text
items, page, perPage, total, filtered, totalPages, hasNext, hasPrevious, sortBy, sortDirection
```

Caller-selectable sorting is limited to `occurred_at`; `id` is used only as the internal deterministic tie-breaker. Pagination normalization, clamping, offset calculation, count execution, and ordering mechanics are delegated to `maatify/persistence`. The public EventLogging API does not expose persistence package classes.

DiagnosticsTelemetry Admin Query exception boundaries:

- `DiagnosticsTelemetryAdminQueryInvalidArgumentException` for invalid request filters and ranges.
- `DiagnosticsTelemetryAdminQueryExecutionException` for invalid pagination configuration or descriptor construction.
- `DiagnosticsTelemetryStorageException` for PDO and pagination execution failures, using the existing `Failed to query DiagnosticsTelemetry records: ...` message pattern.

The package does not provide HTTP controllers, routes, authorization, middleware, UI, exports, localization, dashboards, free-text search, metadata search, arbitrary SQL, joins, caching, or approximate counts for Admin Query. Event ID, route name, duration, and metadata filtering are explicitly unsupported. Hosts own those concerns.

### Superseded Post-v1 Pagination Artifacts

The following pagination artifacts were added after the `v1.0.0` release and are considered superseded experiments pending replacement by the approved Admin Query API:

- `*PaginatedQueryInterface`
- `*QueryCursorDTO`
- `*QueryPageDTO`
- `*PaginatedQueryService`

The AuditTrail, BehaviorTrace, SecuritySignals, and AuthoritativeAudit versions of these artifacts have been removed by their Admin Query implementations because they were unreleased post-v1 experiments, not protected `v1.0.0` contracts. Remaining domains must not use these artifacts as the architecture for new integrations or extend them to additional domains. Advanced domain-scoped Admin Query and reporting contracts remain governed by the approved architecture and roadmap.

Advanced querying (UI-driven generic search, arbitrary filtering, complex host analytics) remains the responsibility of the host application outside this package.


## 13. Public MySQL infrastructure adapters and composition-only status

Classes in `Infrastructure\Mysql\*` namespaces are public infrastructure adapters strictly meant for package composition and wiring when they are repository or writer adapters. This includes domain write repositories, outbox writer repositories, logger repositories, and query repositories.

Host composition roots or DI containers may instantiate these adapters and bind them to domain contracts. `AuditTrailAdminQueryMysqlRepository`, `BehaviorTraceAdminQueryMysqlRepository`, `SecuritySignalsAdminQueryMysqlRepository`, `AuthoritativeAuditAdminQueryMysqlRepository`, and `DiagnosticsTelemetryAdminQueryMysqlRepository` remain the public MySQL adapters for their Admin Query APIs. Application and business code should prefer Admin Query interfaces and other `Contract\*` interfaces rather than concrete MySQL classes. Public adapter status does not make these classes the preferred application-layer API.

Classes marked `@internal` under infrastructure namespaces are package implementation details, not stable public API. Hosts must not construct, type against, extend, or depend on them, and their signatures are not part of the stable compatibility contract. The Admin Query implementations explicitly exclude these internal classes from the stable public API:

- `Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailRowMapper`
- `Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\Pagination\AuditTrailAdminQueryDescriptorBuilder`
- `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper`
- `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder`
- `Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceRowMapper`
- `Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\Pagination\BehaviorTraceAdminQueryDescriptorBuilder`
- `Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryRowMapper`
- `Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\Pagination\DiagnosticsTelemetryAdminQueryDescriptorBuilder`
- `Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsRowMapper`
- `Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\Pagination\SecuritySignalsAdminQueryDescriptorBuilder`

## 14. Failure and exception behavior

Validation belongs at domain boundaries. Commands validate public input; recorders apply policies and construct already-structured write DTOs; repositories enforce storage-specific failures without applying recording policy.

Each domain exposes a domain-specific storage exception. Repository-level storage/read failures are wrapped in the domain-specific exception so hosts can choose their own reliability posture when bypassing recorders.

Every package-defined EventLogging exception implements `Maatify\EventLogging\Exception\EventLoggingExceptionInterface` directly or indirectly. The six currently existing storage exceptions implement it directly:

- `Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException`
- `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException`
- `Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException`
- `Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException`
- `Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException`
- `Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException`

Hosts may catch the package marker when they intentionally need a package-wide EventLogging exception boundary. Each domain-specific exception remains the preferred narrow catch boundary. The marker does not change existing fail-open or fail-closed policies, and external `PDOException` or other propagated external throwables do not implement the marker. Existing constructors, messages, error codes, previous throwables, and failure behavior remain unchanged.

## 15. Fail-open and fail-closed domain boundaries

- **Fail-closed:** `AuthoritativeAudit` is governance/security critical. Its recorder does not accept fallback logger behavior as a substitute for durable persistence and may throw validation or storage exceptions.
- **Fail-open at recorder boundary:** `AuditTrail`, `SecuritySignals`, `BehaviorTrace`, `DiagnosticsTelemetry`, and `DeliveryOperations` catch `Throwable` across the full recording flow, including primitive command construction, validation, policy normalization, DTO construction, repository calls, and fallback logger failures. These domains may accept an optional PSR-3 fallback logger for best-effort reporting before swallowing recorder-boundary failures.

Fail-open behavior applies to recorder boundaries only. Concrete repositories still expose storage exceptions when used directly.

## 15. DTO serialization rules

DTOs are immutable `final readonly` value objects where possible. Package DTOs implement `JsonSerializable` when serialization is safe, structural, and non-display-oriented.

Serialization preserves stored values, formats date-time values with `DATE_ATOM`, serializes domain enum interfaces through their `value()` method, and serializes nested DTOs through their own structural serializer. DTO serialization must not add display labels, translated strings, HTML, route generation, authorization-dependent fields, or host-specific derived data.

No current package DTO is intentionally excluded from `JsonSerializable`. If a future DTO carries a non-serializable resource, closure, stream, lazy host object, or display-oriented view model, that exception must be documented here instead of adding unsafe serialization.

## 16. Schema ownership and canonical table inventory

The authoritative SQL files are owned by each logging domain:

- `src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql`
- `src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql`
- `src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql`
- `src/BehaviorTrace/Database/schema.maa_event_logging_behavior_trace.sql`
- `src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql`
- `src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql`

The package-level `schema/README.md` indexes these locations instead of duplicating SQL files.

Canonical table inventory:

- `maa_event_logging_authoritative_audit_outbox`
- `maa_event_logging_authoritative_audit_log`
- `maa_event_logging_audit_trail`
- `maa_event_logging_security_signals`
- `maa_event_logging_behavior_trace`
- `maa_event_logging_diagnostics_telemetry`
- `maa_event_logging_delivery_operations`

## 17. Framework-agnostic DI binding contract

The package supports framework-agnostic integration by constructor wiring, factories, `EventLoggingProviderFactory`, and optional `EventLoggingBindings::definitions()`.

The DI binding contract is intentionally minimal:

- host provides `PDO`;
- host provides `ClockInterface`;
- host may provide `LoggerInterface`;
- package definitions return plain PHP callables;
- no package code requires a framework container package; and
- no route, middleware, controller, HTTP, console-kernel, or application runtime binding is part of this package.

### Extensibility contract

Hosts may customize supported domain behavior through explicit domain-specific extension points only. The stable extension points are the public policy interfaces and enum interfaces exposed by each domain.

Supported customization includes:

- domain-specific policy interfaces such as `AuthoritativeAuditPolicyInterface`, `AuditTrailPolicyInterface`, `SecuritySignalsPolicyInterface`, `BehaviorTracePolicyInterface`, `DiagnosticsTelemetryPolicyInterface`, and `DeliveryOperationsPolicyInterface`;
- enum interfaces where the current domain exposes them, such as authoritative audit actor types, behavior-trace actor types, diagnostics-telemetry actor types and severities, and delivery actor types;
- custom actor types, severities, validation rules, and metadata normalization policies where the relevant domain contract supports those values; and
- factory and recorder injection of documented policy interfaces where supported by the current constructors and factory `create()` methods.

Extensibility remains domain-specific. Custom implementations must preserve the owning domain's command, DTO, writer/repository, schema, and failure-boundary contracts. Extensibility must not introduce a generic logger, generic DTO, generic recorder, generic table, shared cross-domain policy, or cross-domain write/read behavior.

## 18. Package boundaries and explicit non-goals

The package must not introduce:

- `GenericLogger`
- `GenericLogDTO`
- `GenericRecorder`
- a generic log table
- a cross-domain writer or repository
- admin/customer application folders
- controllers, routes, middleware, permissions, UI screens, exports, analytics, dashboards, or CRUD APIs
- generic search or arbitrary filtering
- host-specific configuration helpers under `App\` or project namespaces

These non-goals preserve independent failure semantics, retention policies, schema designs, and review paths for each logging domain.

## 19. Package-specific standard decisions

- **Canonical root reference:** `EVENT_LOGGING_PACKAGE_REFERENCE.md` is the only root Package Reference.
- **Admin/Customer split:** Not applicable; logging domains are the correct public boundary.
- **Bootstrap bindings:** Present and optional as framework-agnostic callable definitions; not mandatory runtime wiring.
- **Package-level SQL files:** The root `schema/README.md` indexes domain-owned SQL files; authoritative schema remains domain-local.
- **Single module exception hierarchy:** Domain-specific storage exceptions are retained to preserve independent failure semantics.
- **MySQL adapter status:** MySQL classes are public composition adapters, not preferred business APIs.
- **Read-side scope:** Primitive cursor-based domain reads only; advanced querying belongs outside the package.

## 20. Index of detailed supporting documentation

- [README](README.md) — package overview and links.
- [Schema index](schema/README.md) — schema index and table ownership.
- [Testing strategy](TESTING_STRATEGY.md) — package testing strategy.
- [Logging architecture](docs/architecture/LOGGING_ARCHITECTURE.md) — architecture overview.
- [Logging domain rules](docs/architecture/LOGGING_DOMAIN_RULES.md) — domain isolation rules.
- [Storage and schema](docs/architecture/STORAGE_AND_SCHEMA.md) — storage and schema guarantees.
- [Authoritative audit pipeline](docs/architecture/AUTHORITATIVE_AUDIT_PIPELINE.md) — authoritative audit outbox pipeline.
- [Integration surface design](docs/architecture/INTEGRATION_SURFACE_DESIGN.md) — integration surface design.
- [Primitive read/query support design](docs/architecture/PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md) — primitive read/query design.
- [Installation guide](docs/integration/INSTALLATION.md) — installation guide.
- [Factory usage](docs/integration/FACTORY_USAGE.md) — factory/provider usage.
- [Manual wiring](docs/integration/MANUAL_WIRING.md) — manual wiring guide.
- [DI bindings](docs/integration/DI_BINDINGS.md) — optional DI binding guide.
- [Admin read usage](docs/integration/ADMIN_READ_USAGE.md) — admin read usage.
- [Admin query API roadmap](docs/roadmap/ADMIN_QUERY_API_ROADMAP.md) — admin query roadmap.
- [Admin query API architecture](docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md) — admin query architecture.
- [Package building standard](docs/standards/PACKAGE_BUILDING_STANDARD.md) — generic package-reference standard using `{PACKAGE_NAME}_PACKAGE_REFERENCE.md`.
