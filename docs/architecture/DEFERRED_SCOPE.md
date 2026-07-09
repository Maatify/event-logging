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

## 3. Host Application Responsibilities

The package is strictly an infrastructure library. The following capabilities must be implemented by the host application and will never be provided by this package:
- Operational dashboards and logging UIs.
- Advanced search and reporting.
- API endpoints (routes, controllers, middleware).
- Access control and permissions for viewing logs.
