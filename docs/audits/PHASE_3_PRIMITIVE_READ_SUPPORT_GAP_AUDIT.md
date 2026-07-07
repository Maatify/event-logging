# Phase 3 — Primitive Read / Admin Viewing Support Gap Audit

**Date:** 2026-07-07
**Auditor:** Jules
**Status:** Audit Complete, Gaps Identified

## 1. Domain Readiness

### 1.1 Authoritative Audit
* **Status:** Missing all read/query support.
* **Current state:** Only write contracts and outbox repository exist.
* **Missing:**
  * `AuthoritativeAuditQueryInterface`
  * `AuthoritativeAuditQueryMysqlRepository` (targeting `maa_event_logging_authoritative_audit_log`)
  * `AuthoritativeAuditQueryDTO`
  * `AuthoritativeAuditViewDTO`
  * Support for pagination, date range, actor filter, target filter, action filter, correlation_id.

### 1.2 Audit Trail
* **Status:** Partially complete.
* **Current state:** Has `AuditTrailQueryInterface`, `AuditTrailQueryMysqlRepository`, `AuditTrailQueryDTO`, `AuditTrailViewDTO`. Supports cursor pagination, actor filter, event_key, correlation_id, and date range.
* **Missing:**
  * Entity filtering (`entityType`, `entityId`, `subjectType`, `subjectId`) in `AuditTrailQueryDTO` and repository.
  * Request ID filtering in `AuditTrailQueryDTO` and repository.

### 1.3 Security Signals
* **Status:** Missing all read/query support.
* **Current state:** Only write contracts and logger repository exist.
* **Missing:**
  * `SecuritySignalsQueryInterface`
  * `SecuritySignalsQueryMysqlRepository`
  * `SecuritySignalsQueryDTO`
  * `SecuritySignalsViewDTO`
  * Support for pagination, date range, actor filter, signal_type, severity, request_id, correlation_id.

### 1.4 Behavior Trace
* **Status:** Partially complete (Cursor only).
* **Current state:** Has `BehaviorTraceQueryInterface`, `BehaviorTraceQueryMysqlRepository`, `BehaviorTraceCursorDTO`. Only supports cursor pagination.
* **Missing:**
  * `BehaviorTraceQueryDTO` (to replace the direct cursor parameter and add filters).
  * Date range filtering (`after`, `before`).
  * Actor filtering (`actorType`, `actorId`).
  * Entity/target filtering (`entityType`, `entityId`).
  * Action filter (`action`).
  * Request ID & Correlation ID filters.

### 1.5 Diagnostics Telemetry
* **Status:** Partially complete (Cursor only).
* **Current state:** Has `DiagnosticsTelemetryQueryInterface`, `DiagnosticsTelemetryQueryMysqlRepository`, `DiagnosticsTelemetryCursorDTO`. Only supports cursor pagination.
* **Missing:**
  * `DiagnosticsTelemetryQueryDTO`.
  * Date range filtering (`after`, `before`).
  * Actor filtering (`actorType`, `actorId`).
  * Event/Action filter (`eventKey`).
  * Request ID & Correlation ID filters.
  * Severity filter.

### 1.6 Delivery Operations
* **Status:** Missing all read/query support.
* **Current state:** Only write contracts and logger repository exist.
* **Missing:**
  * `DeliveryOperationsQueryInterface`
  * `DeliveryOperationsQueryMysqlRepository`
  * `DeliveryOperationsQueryDTO`
  * `DeliveryOperationsViewDTO`
  * Support for pagination, date range, actor filter, target filter, channel filter, operation_type filter, status filter, request_id, correlation_id.

---

## 2. Shared Criteria Verification

* **Cursor Pagination Support:** Missing or incomplete in most domains. AuditTrail has it via `cursorOccurredAt`/`cursorId`, BehaviorTrace/DiagnosticsTelemetry have it via a dedicated `CursorDTO`. Needs standardization.
* **Stable Ordering:** AuditTrail uses `ORDER BY occurred_at DESC, id DESC`. BehaviorTrace uses `ASC`. Needs standardization (likely `DESC` is preferred for admin viewing).
* **Safe Metadata Decoding:** Implemented correctly in existing repositories (swallows JSON exceptions and returns `null`).

---

## 3. Exception Policy Checkpoints

* **Existing domain-specific exception classes:** Verified. Each domain has its own `StorageException` (e.g., `AuditTrailStorageException`).
* **Fail-open swallowing exists only at Recorder boundary:** Verified. Repositories throw `PDOException` wrapped in domain exceptions.
* **Repositories/infrastructure do not swallow Throwable:** Verified. Exception mapping is present.
* **AuthoritativeAudit remains fail-closed:** Verified.
* **Read/query exceptions follow MODULE_BUILDING_STANDARD:** Domain exceptions are named and extend `RuntimeException`.
* **Named constructors and RuntimeException inheritance where exceptions are created directly:** Confirmed present in domain exception classes.
