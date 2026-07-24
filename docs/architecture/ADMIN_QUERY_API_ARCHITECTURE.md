# Admin Query API Architecture

**Status:** Approved Architecture
**Phase:** Phase 3 Remediation Complete / Phase 4 Active / DeliveryOperations Runtime Complete

## 1. Purpose

This document defines the canonical architecture for Admin pagination, reporting, and dashboard query work inside the `maatify/event-logging` package.

It applies only to work started **after the first stable release (`v1.0.0`)** and must be read together with:

- [ADMIN_QUERY_API_ROADMAP.md](../roadmap/ADMIN_QUERY_API_ROADMAP.md)
- [ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md](../audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md)
- [EVENT_LOGGING_PACKAGE_REFERENCE.md](../../EVENT_LOGGING_PACKAGE_REFERENCE.md)
- [PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md](PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md)

The architecture separates three distinct layers:

1. The published and protected `v1.0.0` Runtime baseline.
2. Incorrect post-v1.0 pagination work that must be rebuilt.
3. The target Admin Query API and later reporting contracts.

No implementation is authorized by this document alone.

## 2. Protected Baseline (`v1.0.0`)

The published `v1.0.0` primitive capabilities must be explicitly protected:

- Stable write, record, factory, schema, policy, and DTO implementations.
- Primitive `read()` and `find()` behavior using raw SQL limit offsets or simple `lastId` / `lastOccurredAt` cursors.
- Exact existing row hydration arrays, default handling, timezone usage, and exceptions.
- Hardcoded query sort keys `ORDER BY occurred_at DESC, id DESC`.
- Implicit limit protections, usually enforced as `max(1, limit)`.
- Reused query placeholders in basic parameters.
- Empty result sets returning identical structure with no results.
- `AuthoritativeAudit`'s strict validation mapping string sizes correctly without truncation.

## 3. Rebuild Scope (Phase 1 Remediation)

Between Phase 1 implementation and stable release, partial cursor-based pagination experiments were added across `AuthoritativeAudit`, `AuditTrail`, `BehaviorTrace`, and `SecuritySignals`. These APIs attempted pagination using an intermediate wrapper over the protected primitive queries.

These wrapper APIs (e.g., `AuthoritativeAuditPaginatedQueryInterface`, `SecuritySignalsQueryCursorDTO`) are now classified as superseded internal experiments. They must not be copied to `DiagnosticsTelemetry` or `DeliveryOperations` and must be deleted atomically when each domain's new Admin pagination API is merged.

## 4. Target Architecture: Admin Query API

The Admin Query API is a new, separate read-only capability for each domain to support administrative dashboards.

### 4.1 Implementation Rules

- Must provide deterministic offset pagination with page normalization and per-page limits.
- Must filter using `COUNT(*)` data metrics that align securely with row output.
- Must provide safe optional host-selected ordering with deterministic tie-breakers.
- Must separate the DTO used to configure pagination from the DTO used to hydrate the database row.
- Must use distinct named PDO placeholders for every bound variable, eliminating duplicate placeholder reuse for complex `metadata` path generation or `JSON_EXTRACT` statements.
- Must depend on `maatify/persistence` purely as an internal mechanical utility, not exposed in the public interface.
- Must not introduce cross-domain `EventLogging` repository abstraction.

### 4.2 Sequence of Work

To minimize merge conflicts and ensure safety, the Admin pagination logic must be completed for one domain at a time, strictly sequenced as defined in [ADMIN_QUERY_API_ROADMAP.md](../roadmap/ADMIN_QUERY_API_ROADMAP.md):

1. `AuditTrail` — proof of concept complete.
2. `BehaviorTrace` — standardizes actor filters.
3. `SecuritySignals` — incorporates enums.
4. `AuthoritativeAudit` — strict validation rules and strict target/action bounds.
5. `DiagnosticsTelemetry` — new implementation for complex schema not previously paginated.
6. `DeliveryOperations` — new implementation after the simpler domains because of its broader state and provider-related query surface.

The six domains must not be implemented as one bulk generic repository or one cross-domain query layer.

Each domain requires its own reviewed contract, filter rules, trusted SQL, mapper, exception policy, tests, and documentation.

## 5. Strict Boundary Responsibilities

### 5.1 Event Logging Package (`maatify/event-logging`)

The package owns all domain-specific behavior:

- Mandatory domain constraints.
- Security, ownership, tenant, and visibility constraints where applicable.
- Domain search and filter construction.
- Domain JOINs and selected columns where explicitly approved.
- Trusted SQL and matching parameters.
- Approved public sort keys and trusted SQL mappings.
- Semantic alignment between total-count, filtered-count, and data queries.
- Row mapping into package/domain DTOs.
- Package-owned request and response contracts.
- Independent exception mapping matching the existing write and query contracts (e.g. `AuthoritativeAuditStorageException`).

### 5.2 Persistence Package (`maatify/persistence`)

The mechanical execution is delegated to `maatify/persistence` via `PdoPaginator`. The `maatify/persistence` package provides generic page normalization, offset logic, bound-parameter formatting, loop mechanics, limit clauses, sort direction enum handling, and total/filtered metadata construction.

## 6. Prohibited Practices

The following are strictly banned during Admin Query implementation:

- Exposing `maatify/persistence` DTOs or exception classes in any Event Logging public API.
- Wrapping a primitive `find()` or `read()` to fake pagination limits instead of writing new SQL.
- Removing or altering the primitive `find()` behavior.
- Altering any domain schema.
- Sharing a generic Request/Response DTO between distinct domains.
- Extending the old wrapper experiment to `DiagnosticsTelemetry` or `DeliveryOperations`.
- Creating one generic repository for all domains.
- Creating generic cross-domain queries.
- Adding HTTP, UI, permission, localization, or export code to this package.
- Copying pagination mechanics from `maatify/persistence`.
- Starting reporting work before all six Admin pagination paths are complete.
- Skipping any domain from the final pagination or reporting coverage.

## 11. Implementation Sequence

The approved implementation sequence is:

1. Phase 2 — `AuditTrail` pagination rebuild POC.
2. Phase 3 — rebuild `BehaviorTrace`, then `SecuritySignals`, then `AuthoritativeAudit`.
3. Phase 4 — add new implementations for `DiagnosticsTelemetry`, then `DeliveryOperations`.
4. Phase 5 — implement reporting and dashboard summary contracts for all six domains.
5. Phase 6 — complete host integration documentation and validation.

## 12. Implementation Gate

Phase 3 Remediation Complete.
Phase 4 Active.
DiagnosticsTelemetry Runtime complete
DeliveryOperations Runtime complete
Reporting/dashboard blocked
No release or tag authorized

- `AuditTrail`: Runtime implemented.
- `BehaviorTrace`: Runtime implemented.
- `SecuritySignals`: Runtime implemented.
- `AuthoritativeAudit`: Runtime implemented.
- `DiagnosticsTelemetry`: Runtime implemented.
- `DeliveryOperations`: Runtime implemented.

Approval of this architecture document alone does not authorize Composer, Runtime, schema, test, tag, or release changes.
