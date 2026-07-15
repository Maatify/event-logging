# Admin Query API Roadmap

**Status:** Phase 0 and Phase 1 Complete / AuditTrail POC Implemented / BehaviorTrace Runtime Implemented / SecuritySignals Runtime Implemented / Pending v1.1.0 Release

## 1. Scope Boundary

This roadmap applies only to Admin pagination, reporting, and dashboard work that started **after the first stable release (`v1.0.0`)**.

The published `v1.0.0` Runtime baseline is frozen and out of scope for remediation. The following first-release behavior and contracts must remain unchanged:

- Event-writing and logging Runtime behavior.
- Existing domain contracts published in `v1.0.0`.
- Existing primitive read/query APIs.
- Existing query DTOs, view/event DTOs, repositories, schemas, and exception behavior.
- Existing cursor behavior that belongs to the first-release Runtime.
- Existing host integrations that depend on the first-release contracts.

No phase in this roadmap may redesign, replace, or remove the first-release Runtime baseline unless a separate explicitly approved compatibility decision authorizes it.

## 2. Post-v1.0 Work Being Corrected and Completed

After `v1.0.0`, pagination work started as a separate feature track. Part of that work was implemented with the wrong architecture and then stopped before all domains were completed.

The post-v1.0 pagination artifacts are not treated as protected `v1.0.0` contracts. They are the remediation scope of this roadmap.

The correct target is a separate Admin Query API using `maatify/persistence v1.1.0` for page/per-page normalization, deterministic sorting, count execution, offset calculation, mapper invocation, and canonical pagination metadata.

The package remains responsible for domain filters, trusted SQL and parameters, selected columns, sort mappings, count/data semantic alignment, row hydration, and package-owned result contracts.

## 3. Domain Classification and Required Order

The pagination work must proceed in this exact order so that incorrectly implemented post-v1.0 work is rebuilt first, then the domains that never received that work are implemented for the first time.

| Order | Domain | Classification | Required action |
|---:|---|---|---|
| 1 | `AuditTrail` | **REBUILD** | Replace the incorrect post-v1.0 pagination experiment with the approved `maatify/persistence`-based Admin Query API POC. |
| 2 | `BehaviorTrace` | **REBUILD** | Rebuild the incorrect post-v1.0 pagination implementation using the approved POC architecture. |
| 3 | `SecuritySignals` | **REBUILD** | Rebuild the incorrect post-v1.0 pagination implementation, delete its superseded wrapper in the same Runtime change set, and preserve only the protected `v1.0.0` security and failure semantics. |
| 4 | `AuthoritativeAudit` | **REBUILD** | Rebuild the incorrect post-v1.0 pagination implementation last among the remediation domains because of its fail-closed behavior and outbox/materialized-log boundary. |
| 5 | `DiagnosticsTelemetry` | **NEW** | Add the Admin Query API pagination path for the first time. No post-v1.0 wrapper remediation exists in this domain. |
| 6 | `DeliveryOperations` | **NEW** | Add the Admin Query API pagination path for the first time after the simpler domains are proven, because of its broader state and provider-related query surface. |

The six domains must not be treated as one bulk implementation. Each domain requires its own reviewed design details, Runtime changes, tests, and documentation update.

## 4. Roadmap Phases

### Phase 0 — Documentation and Architecture Alignment

- **Goal:** Separate the first-release Primitive Read/Query Runtime from the later Admin pagination and reporting track.
- **Status:** Complete.

### Phase 1 — Current Runtime and Post-v1.0 Compatibility Inventory

- **Goal:** Audit the six domains, identify which post-v1.0 pagination work must be rebuilt, identify which domains require a new implementation, and verify compatibility with `maatify/persistence v1.1.0`.
- **Status:** Complete. ([View Audit](../audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md))

### Phase 2 — `AuditTrail` Pagination Rebuild POC
**Status:** [Implemented and Merged / Pending v1.1.0 Release](../architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md)

- **Classification:** Rebuild of incorrect post-v1.0 work.
- **Goal:** Replace the existing post-v1.0 `AuditTrail` pagination experiment with the correct separate Admin Query API architecture using `maatify/persistence`.
- **Requirements:** Approved blueprint, package-owned public contract, shared count/data filter source, mapper strategy, exception translation, sort whitelist, and complete regression coverage proving that `v1.0.0` behavior remains unchanged.
- **Status:** Implemented and Merged / Pending v1.1.0 Release.

### Phase 3 — Remaining Post-v1.0 Pagination Rebuilds

Apply the approved `AuditTrail` POC architecture in this order:

1. `BehaviorTrace` — rebuild. (Runtime implemented)
2. `SecuritySignals` — rebuild. (Runtime implemented)
3. `AuthoritativeAudit` — rebuild last because of its higher operational and authority risk.

For each rebuild domain:

- Replace the incorrect post-v1.0 pagination implementation with the approved package-owned Admin Query API.
- Treat post-v1.0 wrapper interfaces, page DTOs, cursor DTOs, service shapes, serialization, and cursor-wrapper mechanics as remediation inputs rather than protected compatibility contracts.
- Delete the superseded post-v1.0 Runtime/test artifacts in the same Runtime rebuild change set after the replacement and its complete tests are present; do not defer deletion to a later cleanup phase.
- Preserve every `v1.0.0` contract and Runtime behavior.
- Search maintained host repositories and migrate any discovered post-v1 wrapper usage, without keeping the obsolete package API alive or postponing its package-level deletion.
- Do not copy pagination mechanics owned by `maatify/persistence`.

- **Status:**
  - `BehaviorTrace`: [Owner Approved / Runtime Implemented](../architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md)
  - `SecuritySignals`: [Owner Approved / Runtime Implemented](../architecture/ADMIN_QUERY_SECURITY_SIGNALS_REBUILD_BLUEPRINT.md); [post-v1 retirement decision recorded](../architecture/ADMIN_QUERY_SECURITY_SIGNALS_POST_V1_RETIREMENT_DECISION.md)
  - `AuthoritativeAudit`: [Blueprint Proposed / Pending Owner Approval / Runtime Blocked](../architecture/ADMIN_QUERY_AUTHORITATIVE_AUDIT_REBUILD_BLUEPRINT.md)

### Phase 4 — New Pagination Implementations for Missing Domains

Implement the Admin Query API for domains that never received the incorrect post-v1.0 pagination experiment:

1. `DiagnosticsTelemetry` — new implementation.
2. `DeliveryOperations` — new implementation after `DiagnosticsTelemetry` because its query surface is more complex.

These are new post-v1.0 features, not corrections to the first-release Runtime.

- **Status:** Pending completion of all rebuild domains.

### Phase 5 — Reporting and Dashboard Summary Contracts

After all six Admin pagination paths are complete and stable, define and implement reporting and dashboard summary contracts for **all six domains**.

This work is new post-v1.0 functionality for every domain. It must proceed domain by domain and must not be mixed into the pagination remediation PRs.

Default reporting order:

1. `AuditTrail`.
2. `BehaviorTrace`.
3. `SecuritySignals`.
4. `AuthoritativeAudit`.
5. `DiagnosticsTelemetry`.
6. `DeliveryOperations`.

A reporting-specific audit may adjust this order only through an explicit documented decision, but no domain may be omitted.

Reporting scope may include domain-appropriate aggregates, counts, trends, and dashboard summaries. It must not introduce cross-domain reporting queries unless a separate architecture decision explicitly approves them.

- **Status:** Pending completion of pagination across all six domains.

### Phase 6 — Host Integration Documentation and Validation

- **Goal:** Document how host applications wire, authorize, filter, sort, paginate, and expose the completed Admin Query API and reporting contracts.
- **Scope:** Controllers, routes, permissions, UI, localization, and exports remain host-owned.
- **Status:** Pending completion of pagination and reporting contracts.

## 5. Non-Negotiable Compatibility Rules

Every implementation phase must prove all of the following:

1. No first-release `v1.0.0` public contract is removed or changed.
2. Existing primitive read/query behavior remains identical.
3. Existing write/logging Runtime behavior remains identical.
4. Existing schemas remain compatible unless a separately approved migration is strictly required for the new post-v1.0 feature.
5. Existing host integrations that depend on the protected first-release contracts continue to work without modification.
6. Incorrect post-v1.0 pagination artifacts are not preserved merely because they already exist or expose public PHP symbols; rebuild phases replace and delete them atomically.
7. Post-v1 wrapper page/cursor contracts and cursor-wrapper mechanics are not compatibility targets unless a separate explicit Owner decision says otherwise.
8. Host repositories are searched and migrated as integration work, but host migration does not keep superseded package artifacts alive or defer deletion to a later package cleanup.
9. No domain is skipped from the final pagination and reporting coverage.

## 6. Current Gate

The `AuditTrail` Admin Query Runtime is implemented and merged, pending the future `v1.1.0` release.

The `BehaviorTrace` Admin Query Runtime is implemented and complete.

The `SecuritySignals` Admin Query Runtime is implemented and complete, pending only the future package release where applicable. The replacement API is present, the protected primitive behavior is preserved, the required distinct-placeholder correction is applied, the exact superseded Runtime/test artifacts are deleted, and strict real-MySQL Integration coverage is present.

`AuthoritativeAudit` blueprint is Proposed / Pending Owner Approval / Runtime Blocked.

`DiagnosticsTelemetry` remains blocked.

`DeliveryOperations` remains blocked.

Reporting and dashboard work remain blocked.

No tag or release is authorized.
