# Admin Read Usage

> **Scope Boundary Notice:** This guide focuses on the currently supported primitive `v1.0.0` read/query path. The existing post-v1 pagination wrappers are superseded experiments and must not be used for new integrations. This includes:
> - `*PaginatedQueryInterface`
> - `*QueryCursorDTO`
> - `*QueryPageDTO`
> - `*PaginatedQueryService`
>
> For the future replacement path, see the [Admin Query API Architecture](../architecture/ADMIN_QUERY_API_ARCHITECTURE.md) and [Roadmap](../roadmap/ADMIN_QUERY_API_ROADMAP.md).

The `maatify/event-logging` library provides primitive read/query contracts, strictly scoped to each domain, intended to serve as the foundation for administrative viewing capabilities.

**Note: The package does not provide generic readers, admin controllers, routes, middleware, permissions, UI dashboards, exports, complex analytics, labels/localization, or actor resolution.** The host application retains complete responsibility for building out those features on top of these primitive query interfaces.

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
