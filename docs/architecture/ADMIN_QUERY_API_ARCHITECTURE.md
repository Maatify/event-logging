# Admin Query API Architecture

**Status:** Approved Architecture / Active Post-v1.0 Pagination Remediation
**Phase:** Phase 3 Rebuilds Complete (AuditTrail, BehaviorTrace, SecuritySignals, AuthoritativeAudit Runtime Implemented) / Phase 4 DiagnosticsTelemetry Blueprint Owner Review Pending / DiagnosticsTelemetry and DeliveryOperations Runtime Blocked

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

## 2. Protected `v1.0.0` Runtime Baseline

The first stable release remains the canonical current Runtime and public API baseline.

The following are frozen and outside the remediation scope of this architecture:

- Event-writing and logging Runtime behavior.
- Public domain contracts released in `v1.0.0`.
- Primitive read/query APIs across all six logging domains.
- Existing query DTOs and view/event DTOs.
- Existing repositories, schemas, row hydration behavior, and domain exceptions.
- Existing cursor behavior that belongs to the first-release Runtime.
- Existing host integrations that depend on first-release contracts.

No Admin pagination or reporting phase may redesign, replace, remove, or silently alter this baseline.

Any internal refactor required by later work must prove through regression coverage that all first-release behavior remains identical.

## 3. Incorrect Post-v1.0 Pagination Experiment

After `v1.0.0`, a separate pagination feature track introduced additional artifacts in four domains:

- `AuditTrail`
- `BehaviorTrace`
- `SecuritySignals`
- `AuthoritativeAudit`

These artifacts include combinations of:

- `*PaginatedQueryInterface`
- cursor/page DTOs
- `*PaginatedQueryService`

This work is not part of the protected `v1.0.0` baseline.

It was implemented using an architecture that does not follow the approved `maatify/persistence v1.1.0` package standard and was stopped before all six domains were covered.

Therefore:

- It must not be generalized or copied to additional domains.
- It must not be preserved merely because it already exists.
- Each affected domain must be rebuilt through the approved Admin Query API architecture.
- Superseded post-v1.0 pagination artifacts may be removed or retired only after the replacement passes its complete implementation, test, and compatibility gate.
- Removal or retirement must not affect any `v1.0.0` contract or Runtime behavior.

## 4. Target Admin Query API

The target Admin Query API is a separate, offset/page-based execution path designed for admin grids, filtering, sorting, and deterministic pagination.

It does not replace the primitive Runtime.

It must cover all six domains in the order fixed by the roadmap.

### 4.1 Rebuild domains

These domains already contain incorrect post-v1.0 pagination work and must be rebuilt:

1. `AuditTrail` — rebuild POC.
2. `BehaviorTrace` — rebuild.
3. `SecuritySignals` — rebuild.
4. `AuthoritativeAudit` — rebuild last among remediation domains because of its fail-closed behavior and outbox/materialized-log boundary. (Runtime Implemented)

### 4.2 New implementation domains

These domains never received the incorrect post-v1.0 pagination experiment and require a new Admin Query API path:

5. `DiagnosticsTelemetry` — new implementation.
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
- Translation of persistence exceptions into the approved package exception boundary.
- Regression protection for all `v1.0.0` Runtime behavior.

### 5.2 Pagination Owner (`maatify/persistence v1.1.0+`)

`maatify/persistence` exclusively owns generic pagination mechanics:

- Page and per-page normalization.
- Total and filtered count execution.
- Offset calculation.
- Deterministic sorting execution.
- Sort whitelist enforcement.
- Tie-breaker behavior.
- `LIMIT` and `OFFSET` handling.
- Mapper invocation.
- Canonical pagination metadata.
- Generic pagination query validation and execution errors.

The event-logging package must not copy, fork, or reimplement these mechanics.

### 5.3 Host Application

The host application retains responsibility for integration concerns:

- HTTP controllers and routes.
- Permissions and user authorization.
- Actor, target, entity, or subject name resolution.
- Localization.
- UI screens and tables.
- HTTP response mapping.
- Exports.

These concerns must not be moved into the package.

## 6. Count and Data Semantic Alignment

For every domain implementation:

- `total` counts rows under mandatory package/domain constraints without optional Admin filters.
- `filtered` counts rows under the same mandatory constraints plus all accepted Admin filters.
- The data query uses exactly the same mandatory and optional filter semantics as `filtered`.
- Filtered-count and data SQL must be generated from one shared semantic source of truth.
- Null handling, date boundaries, type/id pairs, parameter normalization, and security constraints must not diverge between count and data queries.

No current count/data alignment is assumed merely because primitive cursor queries already exist.

Every domain must prove alignment through focused unit and integration tests.

## 7. Sorting and SQL Safety

Each domain must define an explicit public sort whitelist.

The implementation must:

- Map public sort keys to trusted SQL identifiers.
- Reject or normalize invalid sort input according to the approved contract.
- Define a deterministic default sort.
- Define a deterministic tie-breaker.
- Never accept arbitrary column names.
- Never include paginator-owned `ORDER BY`, `LIMIT`, or `OFFSET` inside the supplied data SQL.
- Follow all `PdoPaginationQueryDescriptor` SQL and parameter restrictions.
- Use explicit selected columns rather than `SELECT *` for the new Admin path.

## 8. Reporting and Dashboard Contracts

Reporting and dashboard summary work is a separate post-v1.0 phase.

It begins only after pagination is complete and stable across all six domains.

Reporting must cover all six domains, domain by domain, and may include:

- Counts.
- Trends.
- Domain-specific aggregates.
- Dashboard summary contracts.

Reporting work must not be mixed into pagination remediation PRs.

Cross-domain reporting queries remain prohibited unless a separate approved architecture decision explicitly authorizes them.

## 9. Current Contract Precedence

- [EVENT_LOGGING_PACKAGE_REFERENCE.md](../../EVENT_LOGGING_PACKAGE_REFERENCE.md) remains the canonical current stable Runtime and public API contract.
- [PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md](PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md) remains authoritative for the first-release primitive query path.
- [ADMIN_QUERY_API_ROADMAP.md](../roadmap/ADMIN_QUERY_API_ROADMAP.md) defines the approved post-v1.0 execution order.
- [ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md](../audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md) defines the historical Phase 1 baseline. Current per-domain truth is established by the latest approved/reviewable domain blueprint and actual main state.
- This architecture becomes Runtime truth only through separately approved implementation PRs and a later release update.

## 10. Absolute Prohibitions

The following are prohibited:

- Modifying or replacing the published `v1.0.0` Runtime baseline as part of pagination remediation.
- Treating incorrect post-v1.0 pagination artifacts as protected first-release contracts.
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

Runtime implementation remains blocked until the Owner explicitly approves the relevant phase blueprint.

The current next phase is `DiagnosticsTelemetry`.

Approval must cover at minimum:

- The domain replacement strategy (or new implementation strategy).
- Public request and result contracts.
- Filter and sort contracts.
- Count/data semantic alignment.
- Mapper extraction strategy.
- Exception translation.
- Exact proposed file inventory.
- Complete unit, integration, static-analysis, and compatibility test matrix.
- The explicit primitive placeholder correction (`cursor_at_before` and `cursor_at_equal`) to support native prepared statements.

Note that `maatify/persistence ^1.1.0` is already installed. No Composer dependency addition is required.

- `AuditTrail`: Runtime implemented.
- `BehaviorTrace`: Runtime implemented.
- `SecuritySignals`: Runtime implemented.
- `AuthoritativeAudit`: Runtime implemented.
- `DiagnosticsTelemetry`: Blueprint Owner Review Pending. Next authorized implementation.

Approval of this architecture document alone does not authorize Composer, Runtime, schema, test, tag, or release changes.
