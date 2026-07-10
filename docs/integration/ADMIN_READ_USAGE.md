# Admin Read Usage

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

## Query Limits and Ordering

The primitive query interfaces expose domain-specific filters and a `limit` value for bounded reads. Some query DTOs and repositories still expose `cursorOccurredAt` and `cursorId` fields for existing primitive read traversal compatibility, but this package no longer provides a cursor-based paginated query service or page/cursor DTO abstraction.

Do not treat those primitive cursor fields as an approved package pagination pattern. The package-level pagination source of truth is Section 11 of `docs/standards/PACKAGE_BUILDING_STANDARD.md`; no standard-based pagination implementation is provided here yet.

The queries maintain stable `ORDER BY occurred_at DESC, id DESC` ordering where supported by the domain repository.

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
