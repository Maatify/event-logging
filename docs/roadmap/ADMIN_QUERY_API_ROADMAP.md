# Admin Query API Roadmap

**Status:** Active Execution
**Target:** Replace interim wrappers with deterministic pagination and new reporting APIs.

## Executive Summary

The `maatify/event-logging` v1.0.0 release protected the essential primitive write and query boundaries for six domains. However, post-v1.0 experiments introduced interim, inconsistent cursor wrappers across four domains.

This roadmap sequences the replacement of those interim wrappers with a standardized Admin Query API powered by `maatify/persistence`, followed by new implementation for the remaining domains, and finally, cross-domain reporting and dashboard contracts.

## Rules of Engagement

1. **Strictly Sequenced:** Work must proceed phase by phase and domain by domain. Bulk implementation is prohibited.
2. **Domain Isolation:** Each domain must receive its own distinct contracts, tests, and documentation. No generic shared repositories are allowed.
3. **Protected Primates:** The v1.0.0 primitive `find()` and `read()` interfaces must not be modified or removed.
4. **Approval Gates:** Each phase and each domain requires explicit Owner approval (Blueprint) before Runtime implementation is authorized.

## Phases

### Phase 1 — Inventory and Baselines

- **Status:** Complete.
- **Outcome:** The [Admin Query Phase 1 Runtime Compatibility Inventory](../audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md) was created to explicitly document the protected baseline and the artifacts slated for deletion.

### Phase 2 — Proof of Concept Rebuild

- **Status:** Complete.
- **Outcome:** The `AuditTrail` domain was selected as the Proof of Concept. The [AuditTrail Blueprint](../architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md) was approved and implemented. This validated the `maatify/persistence` integration and established the standard for the remaining domains.

### Phase 3 — Pagination Remediation for Remaining Wrapper Domains

Remediate the three remaining domains that received the incorrect post-v1.0 pagination experiment.

The strict order is:

1. `BehaviorTrace` — standardizes actor and action filters.
2. `SecuritySignals` — incorporates severity and classification enums.
3. `AuthoritativeAudit` — the most critical, enforcing strict validation boundaries.

- **Status:** Phase 3 remediation is complete.
  - `BehaviorTrace`: [Owner Approved / Runtime Implemented / Complete](../architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md)
  - `SecuritySignals`: [Owner Approved / Runtime Implemented / Complete](../architecture/ADMIN_QUERY_SECURITY_SIGNALS_REBUILD_BLUEPRINT.md)
  - `AuthoritativeAudit`: [Owner Approved / Runtime Implemented / Complete](../architecture/ADMIN_QUERY_AUTHORITATIVE_AUDIT_BLUEPRINT.md)

### Phase 4 — New Pagination Implementations for Missing Domains

Implement the Admin Query API for domains that never received the incorrect post-v1.0 pagination experiment:

1. `DiagnosticsTelemetry` — new implementation.
2. `DeliveryOperations` — new implementation after `DiagnosticsTelemetry` because its query surface is more complex.

These are new post-v1.0 features, not corrections to the first-release Runtime.

- **Status:** Phase 4 is complete. All six domains now implement the Admin Query API.
  - `DiagnosticsTelemetry`: [Owner Approved / Runtime Implemented / Complete](../architecture/ADMIN_QUERY_DIAGNOSTICS_TELEMETRY_BLUEPRINT.md)
  - `DeliveryOperations`: [Owner Approved / Runtime Implemented / Complete](../architecture/ADMIN_QUERY_DELIVERY_OPERATIONS_BLUEPRINT.md)

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

- **Status:** Blocked. Phase 5 is blocked until Phase 4 (Admin pagination for all domains) is fully complete, documented, and released.

### Phase 6 — Integration and Completion

After all domains receive full pagination and reporting contracts:

1. Finalize host integration documentation.
2. Perform a comprehensive documentation gap analysis.
3. Tag and release the completed Admin Query API capabilities.

- **Status:** Blocked. Phase 6 is blocked until Phase 5 is complete.
