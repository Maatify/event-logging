# Public API

The public entry points are the domain-specific contracts, DTOs, enums, recorders, policies, exceptions, and storage repositories under these namespaces:

- `Maatify\EventLogging\AuthoritativeAudit\Command\*`
- `Maatify\EventLogging\AuthoritativeAudit\Contract\*`
- `Maatify\EventLogging\AuthoritativeAudit\DTO\*`
- `Maatify\EventLogging\AuthoritativeAudit\Enum\*`
- `Maatify\EventLogging\AuthoritativeAudit\Exception\*`
- `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\*`
- `Maatify\EventLogging\AuthoritativeAudit\Recorder\*`
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

No `App\`, project DI/container, project helper, or host-application-specific configuration API is part of the exported package surface.

> **Infrastructure Notice**
>
> Classes in `Infrastructure\**` namespaces (e.g., MySQL repositories) are internal implementation details. They should not be used directly by the application layer. Applications should bind to the interfaces in the `Contract\` namespaces via Dependency Injection.

## Primitive read/query API

Phase 3 exposes only domain-specific primitive query contracts, DTOs, and MySQL repositories for host-owned admin viewing foundations. The package does not provide generic readers, admin controllers, routes, middleware, permissions, exports, analytics, or CRUD APIs.

- Authoritative audit: `AuthoritativeAuditQueryInterface`, `AuthoritativeAuditQueryDTO`, `AuthoritativeAuditViewDTO`, and `AuthoritativeAuditQueryMysqlRepository` query `maa_event_logging_authoritative_audit_log` with actor, target, action, correlation, date-range, cursor, and limit filters. It intentionally has no `requestId` filter because the authoritative audit log table does not store `request_id`.
- Audit trail: `AuditTrailQueryInterface`, `AuditTrailQueryDTO`, `AuditTrailViewDTO`, and `AuditTrailQueryMysqlRepository` support actor, event key, entity, subject, request, correlation, date-range, cursor, and limit filters.
- Security signals: `SecuritySignalsQueryInterface`, `SecuritySignalsQueryDTO`, `SecuritySignalsViewDTO`, and `SecuritySignalsQueryMysqlRepository` support actor, signal type, severity, request, correlation, date-range, cursor, and limit filters.
- Behavior trace: `BehaviorTraceQueryInterface::find(BehaviorTraceQueryDTO $query)` adds query-based reads for actor, entity, action, request, correlation, date-range, cursor, and limit filters. The legacy cursor `read(?BehaviorTraceCursorDTO $cursor, int $limit = 100)` method remains for backward compatibility.
- Diagnostics telemetry: `DiagnosticsTelemetryQueryInterface::find(DiagnosticsTelemetryQueryDTO $query)` adds query-based reads for actor, event key, severity, request, correlation, date-range, cursor, and limit filters. The legacy cursor `read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100)` method remains for backward compatibility.
- Delivery operations: `DeliveryOperationsQueryInterface`, `DeliveryOperationsQueryDTO`, `DeliveryOperationsViewDTO`, and `DeliveryOperationsQueryMysqlRepository` support actor, target, channel, operation type, status, request, correlation, date-range, cursor, and limit filters.

All primitive query repositories order results by `occurred_at DESC, id DESC`, apply descending cursor pagination with `cursorOccurredAt` and `cursorId`, safely decode JSON payload/metadata fields to arrays, return `null` for corrupt JSON, wrap read/storage failures in the domain-specific storage exception, and provide **fail-safe hydration** (e.g. gracefully handling invalid enum values in the DB by sanitizing or falling back rather than throwing).

> **Reader Scope & Limitations**
>
> The query interface exposed by this module represents a **primitive, cursor-based read-side**.
>
> It is designed for:
> - Archiving
> - Sequential processing
> - Export and migration jobs
>
> It is **not designed** to support:
> - UI pagination
> - Searching or filtering
> - Aggregations or analytics
>
> Any advanced or UI-driven querying MUST be implemented outside the module, using application-level services or optional utilities built on top of the module contracts.
