# Deferred Scope & Future Considerations

**Status:** CANONICAL
**Scope:** Documents capabilities, strategies, and implementation details that are deferred to future phases and are **not part of the v1.0.0 runtime baseline**.

---

## 1. Archiving Strategy

While the unified logging architecture contemplates archiving to manage large data volumes, **archive support is deferred and optional**. It is not required for the correct functioning of the `maatify/event-logging` package.

**Deferred Archive Features:**
- Dedicated `*_archive` tables (e.g., `maa_event_logging_behavior_trace_archive`).
- MySQL-to-MySQL archival workers and checkpointing mechanisms.
- Retention policies (e.g., moving data older than 90 days to cold storage).
- "Hot + Archive" union read strategies for querying across active and archived records.

**Explicitly Unsupported:**
- MongoDB archiving is explicitly rejected and unsupported.

## 2. AuthoritativeAudit Outbox Materialization

The Authoritative Audit domain utilizes a transactional outbox (`maa_event_logging_authoritative_audit_outbox`) to ensure fail-closed guarantees during the host application's business transaction.

**Deferred Features:**
- Outbox consumer/materializer scripts.
- Dead-letter queue (DLQ) semantics or manual intervention flows for consumer failures.
- Cross-database synchronization via the outbox.

For the current baseline, the outbox acts as the authoritative source of truth, and moving data from the outbox to the `maa_event_logging_authoritative_audit_log` (schema/read model) is the responsibility of host-implemented consumer workers.

## 3. Host Application Responsibilities vs. Package Scope

The package is strictly an infrastructure library.

**Strictly Forbidden (Always Host Responsibility):**
The following capabilities must be implemented by the host application and will **never** be provided by this package:
- UI dashboards and frontend components.
- HTTP/API endpoints (routes, controllers, middleware).
- Access control and permissions for viewing logs.
- Host-specific search and reporting implementations (e.g., specific CSV exports or cross-table JOINs).

**Future Package Scope (Deferred):**
While the above are strictly forbidden, providing a generalized PHP-level query interface is considered future scope. See the [Admin Query API Roadmap](../roadmap/ADMIN_QUERY_API_ROADMAP.md) for details on planned:
- PHP-level Admin Query API contracts and DTOs.
- Domain-scoped admin listing and dashboard summary read models.
