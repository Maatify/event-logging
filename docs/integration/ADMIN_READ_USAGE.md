# Admin Read Usage

> **Scope Boundary Notice:** This guide covers the protected primitive `v1.0.0` read/query path and the separate AuthoritativeAudit, AuditTrail, BehaviorTrace, SecuritySignals, and DiagnosticsTelemetry Admin Query APIs. The existing post-v1 pagination wrappers are superseded experiments and must not be used for new integrations. This includes:
> - `*PaginatedQueryInterface`
> - `*QueryCursorDTO`
> - `*QueryPageDTO`
> - `*PaginatedQueryService`
>
> AuthoritativeAudit, AuditTrail, BehaviorTrace, and SecuritySignals use rebuilt/replacement Admin Query paths. DiagnosticsTelemetry uses a new Admin Query implementation. DeliveryOperations blueprint/design and Owner approval are next; see the [Admin Query API Architecture](../architecture/ADMIN_QUERY_API_ARCHITECTURE.md) and [Roadmap](../roadmap/ADMIN_QUERY_API_ROADMAP.md).

The `maatify/event-logging` library provides both protected primitive read/query contracts and separate Admin Query offset pagination contracts, strictly scoped to each domain, intended to serve as the foundation for administrative viewing capabilities.

**Note: The package does not provide generic readers, admin controllers, routes, middleware, permissions, UI dashboards, exports, complex analytics, labels/localization, or actor resolution.** The host application retains complete responsibility for building out those features on top of these query interfaces.

## Domain-Specific Query Contracts

Each domain provides its own set of distinct contracts, query DTOs, view DTOs, and MySQL repository implementations. This ensures domain isolation across read operations just as it does for writes.

### Query API Architecture

For any given domain, the general pattern is:

1. **`{Domain}QueryInterface`**: The contract defining the available read methods (typically a `find` method).
2. **`{Domain}QueryDTO`**: The object used to pass filter criteria and pagination cursors.
3. **`{Domain}ViewDTO`**: The read-only model returned by the query, representing a single log record.
4. **`{Domain}QueryMysqlRepository`**: The concrete MySQL implementation of the query interface.

### Example: Querying the Audit Trail

```php
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;

$repository = new AuditTrailQueryMysqlRepository($pdo);

$queryDto = new AuditTrailQueryDTO(
    actorType: 'admin',
    actorId: 123,
    eventKey: 'user.created',
    limit: 50
);

/** @var \Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO[] $results */
$results = $repository->find($queryDto);
```

## Cursor Pagination

The primitive query interfaces enforce robust cursor pagination to ensure stable ordering and efficient deep-linking. Pagination is handled using standard properties on the query DTOs:

- `cursorOccurredAt`: The timestamp of the last record seen (descending).
- `cursorId`: The database ID of the last record seen (descending, for tie-breaking).
- `limit`: The maximum number of records to return.

The queries rigidly maintain a stable `ORDER BY occurred_at DESC, id DESC` to guarantee consistent traversal of the log data.

## AuditTrail Admin Query Offset Pagination

AuditTrail also exposes a separate public Admin Query contract for offset pagination:

```php
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailAdminQueryMysqlRepository;

$repository = new AuditTrailAdminQueryMysqlRepository($pdo);

$page = $repository->paginate(new AuditTrailAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    eventKey: 'customer.view',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

Supported filters are `actorType`, `actorId`, `eventKey`, `entityType`, `entityId`, `subjectType`, `subjectId`, `requestId`, `correlationId`, `after`, and `before`. ID filters require the matching type filter; type-only filters are valid. Date boundaries are inclusive and equal boundaries are valid.

The response serializes with `items`, `page`, `perPage`, `total`, `filtered`, `totalPages`, `hasNext`, `hasPrevious`, `sortBy`, and `sortDirection`. Caller-selectable sorting is limited to `occurred_at`; `id` is reserved as the internal tie-breaker. Pagination mechanics are delegated to `maatify/persistence`, but no persistence classes are exposed through the public EventLogging contract.

Admin Query validation errors throw `AuditTrailAdminQueryInvalidArgumentException`. Pagination descriptor/configuration failures throw `AuditTrailAdminQueryExecutionException`. PDO and pagination execution failures throw `AuditTrailStorageException` using the existing audit-trail storage message pattern.

## BehaviorTrace Admin Query Offset Pagination

BehaviorTrace also exposes a separate public Admin Query contract for offset pagination:

```php
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceAdminQueryMysqlRepository;

$repository = new BehaviorTraceAdminQueryMysqlRepository($pdo);

$page = $repository->paginate(new BehaviorTraceAdminQueryRequestDTO(
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

Supported filters are `actorType`, `actorId`, `action`, `entityType`, `entityId`, `requestId`, `correlationId`, `after`, and `before`. ID filters require the matching type filter; type-only filters are valid. Date boundaries are inclusive and equal boundaries are valid.

The response serializes with `items`, `page`, `perPage`, `total`, `filtered`, `totalPages`, `hasNext`, `hasPrevious`, `sortBy`, and `sortDirection`. Caller-selectable sorting is limited to `occurred_at`; `id` is reserved as the internal tie-breaker. Pagination mechanics are delegated to `maatify/persistence`, but no persistence classes are exposed through the public EventLogging contract.

Admin Query validation errors throw `BehaviorTraceAdminQueryInvalidArgumentException`. Pagination descriptor/configuration failures throw `BehaviorTraceAdminQueryExecutionException`. PDO and pagination execution failures throw `BehaviorTraceStorageException` using the existing `Failed to query BehaviorTrace records: ...` message pattern.

## SecuritySignals Admin Query Offset Pagination

SecuritySignals also exposes a separate public Admin Query contract for offset pagination:

```php
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsAdminQueryMysqlRepository;

$repository = new SecuritySignalsAdminQueryMysqlRepository($pdo);

$page = $repository->paginate(new SecuritySignalsAdminQueryRequestDTO(
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

Supported filters are `actorType`, `actorId`, `signalType`, `severity`, `requestId`, `correlationId`, `after`, and `before`. SecuritySignals actor filters are independent: `actorType` only, `actorId` only, both together, and neither are all valid. Date boundaries are inclusive and equal boundaries are valid.

The response serializes with `items`, `page`, `perPage`, `total`, `filtered`, `totalPages`, `hasNext`, `hasPrevious`, `sortBy`, and `sortDirection`. Caller-selectable sorting is limited to `occurred_at`; `id` is reserved as the internal tie-breaker. Pagination mechanics are delegated to `maatify/persistence`, but no persistence classes are exposed through the public EventLogging contract.

Admin Query validation errors throw `SecuritySignalsAdminQueryInvalidArgumentException`. Pagination descriptor/configuration failures throw `SecuritySignalsAdminQueryExecutionException`. PDO and pagination execution failures throw `SecuritySignalsStorageException` using the existing `Failed to query SecuritySignals records: ...` message pattern.

## AuthoritativeAudit Admin Query Offset Pagination

AuthoritativeAudit also exposes a separate public Admin Query contract for offset pagination:

```php
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository;

$repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

$page = $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    action: 'role.assign',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

Supported filters are `eventId`, `actorType`, `actorId`, `targetType`, `targetId`, `action`, `correlationId`, `after`, and `before`. Date boundaries are inclusive and equal boundaries are valid. Independent actor/target filters are supported.

The response serializes with `items`, `page`, `perPage`, `total`, `filtered`, `totalPages`, `hasNext`, `hasPrevious`, `sortBy`, and `sortDirection`. Caller-selectable sorting is limited to `occurred_at`; `id` is reserved as the internal tie-breaker. Pagination mechanics are delegated to `maatify/persistence`, but no persistence classes are exposed through the public EventLogging contract.

Admin Query validation errors throw `AuthoritativeAuditAdminQueryInvalidArgumentException`. Pagination descriptor/configuration failures throw `AuthoritativeAuditAdminQueryExecutionException`. PDO and pagination execution failures throw `AuthoritativeAuditStorageException` using the existing `Failed to query AuthoritativeAudit records: ...` message pattern.

## DiagnosticsTelemetry Admin Query Offset Pagination

## DeliveryOperations Admin Query Offset Pagination

DeliveryOperations also exposes a separate public Admin Query contract for offset pagination:

```php
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsAdminQueryMysqlRepository;

$repository = new DeliveryOperationsAdminQueryMysqlRepository($pdo);

$page = $repository->paginate(new DeliveryOperationsAdminQueryRequestDTO(
    channel: 'email',
    operationType: 'notification_send',
    status: 'success',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

Supported filters include `id`, `eventId`, `channel`, `operationType`, `actorType`, `actorId`, `targetType`, `targetId`, `status`, `attemptNoMin`, `attemptNoMax`, `correlationId`, `requestId`, `provider`, `providerMessageId`, `errorCode`, `errorMessageLike`, `metadataFilters`, `scheduledAfter`, `scheduledBefore`, `completedAfter`, `completedBefore`, `after`, `before`, and `nullStateFilters`.

The response serializes with `items`, `page`, `perPage`, `total`, `filtered`, `totalPages`, `hasNext`, `hasPrevious`, `sortBy`, and `sortDirection`. Caller-selectable sorting is limited to `occurred_at`; `id` is reserved as the internal tie-breaker. Pagination mechanics are delegated to `maatify/persistence`, but no persistence classes are exposed through the public EventLogging contract.

Admin Query validation errors throw `DeliveryOperationsAdminQueryInvalidArgumentException`. Pagination descriptor/configuration failures throw `DeliveryOperationsAdminQueryExecutionException`. PDO and pagination execution failures throw `DeliveryOperationsStorageException`.

## DiagnosticsTelemetry Admin Query Offset Pagination

DiagnosticsTelemetry also exposes a separate public Admin Query contract for offset pagination:

```php
use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryAdminQueryMysqlRepository;

$repository = new DiagnosticsTelemetryAdminQueryMysqlRepository($pdo);

$page = $repository->paginate(new DiagnosticsTelemetryAdminQueryRequestDTO(
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

Supported filters are `actorType`, `actorId`, `eventKey`, `severity`, `requestId`, `correlationId`, `after`, and `before`. DiagnosticsTelemetry actor filters are independent: `actorType` only, `actorId` only, both together, and neither are all valid. Date boundaries are inclusive and equal boundaries are valid.

The response serializes with `items`, `page`, `perPage`, `total`, `filtered`, `totalPages`, `hasNext`, `hasPrevious`, `sortBy`, and `sortDirection`. Caller-selectable sorting is limited to `occurred_at`; `id` is reserved as the internal tie-breaker. Pagination mechanics are delegated to `maatify/persistence`, but no persistence classes are exposed through the public EventLogging contract.

Admin Query validation errors throw `DiagnosticsTelemetryAdminQueryInvalidArgumentException`. Pagination descriptor/configuration failures throw `DiagnosticsTelemetryAdminQueryExecutionException`. PDO and pagination execution failures throw `DiagnosticsTelemetryStorageException` using the existing `Failed to query DiagnosticsTelemetry records: ...` message pattern.

Unsupported filters: eventId, routeName, durationMs, metadata, free-text, generic, and arbitrary-SQL filtering are explicitly unsupported.

## Supported Filters per Domain

Each domain's query DTO exposes specific filter properties aligned with its context. Common filters include `actorType`, `actorId`, `after`, `before`, `requestId`, and `correlationId`.

- **AuthoritativeAudit**: Actor, target, action, correlation, date-range. *(Note: Intentionally no `requestId` filter as it is not stored in this domain).*
- **AuditTrail**: Actor, event key, entity, subject, request, correlation, date-range.
- **SecuritySignals**: Actor, signal type, severity, request, correlation, date-range.
- **BehaviorTrace**: Actor, entity, action, request, correlation, date-range.
- **DiagnosticsTelemetry**: Actor, event key, severity, request, correlation, date-range.
- **DeliveryOperations**: Actor, target, channel, operation type, status, request, correlation, date-range.

## Safe JSON Decoding

The MySQL repositories employ safe metadata and payload decoding when parsing log records. Valid JSON is safely returned as an array, while corrupt JSON strings gracefully return `null`. This prevents an entire database row/page from failing due to localized data corruption.

## Read Exceptions

Database connectivity or execution failures encountered during querying are not silently swallowed. Instead, they are wrapped and thrown as domain-specific storage exceptions (e.g., `Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException`). The host application should catch and handle these accordingly when rendering administrative views.
