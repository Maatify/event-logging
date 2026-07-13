# Admin Query API Roadmap

**Status:** Phase 0 Complete / Runtime Implementation Blocked

## 1. Overview
This roadmap outlines the plan for introducing the Admin Query API. The current Primitive Read/Query Runtime relies on primitive cursor query repositories that already exist across all six logging domains and will remain unchanged. A separate paginated wrapper experiment exists only in four domains (`AuditTrail`, `AuthoritativeAudit`, `SecuritySignals`, and `BehaviorTrace`); this wrapper experiment will not be generalized or extended to `DiagnosticsTelemetry` or `DeliveryOperations`. The true Admin Query API is a completely separate offset/page-based execution path that relies on external pagination mechanics provided by `maatify/persistence v1.1.0`. Implementation of the true Admin Query API is strictly deferred pending Owner approval.

## 2. Roadmap Phases

### Phase 0 — Documentation and Architecture Alignment
* **Goal:** Align all documentation to separate the current Primitive Read/Query Runtime from the future Admin Query API.
* **Status:** Complete.

### Phase 1 — Current Runtime and Compatibility Inventory
* **Goal:** Audit current domain schema, queries, and DTO implementations to ensure readiness for offset pagination.
* **Status:** Complete. ([View Audit](../audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md))

### Phase 2 — Single-Domain Admin Pagination POC using `maatify/persistence`
* **Goal:** Implement the Admin Query API adapter in one domain (e.g. `AuthoritativeAudit`) using `maatify/persistence` to validate the design.
* **Status:** Pending Owner Approval.

### Phase 3 — Domain-by-Domain Adoption after POC approval
* **Goal:** Roll out the validated adapter implementation across all remaining event-logging domains.
* **Status:** Pending.

### Phase 4 — Dashboard and Reporting Summary Contracts
* **Goal:** Define specific interfaces/contracts for high-level aggregate reporting, metrics, or dashboard counts within appropriate domains.
* **Status:** Pending.

### Phase 5 — Host Integration Documentation and Validation
* **Goal:** Provide detailed guides for host applications on how to wire, filter, and serve the Admin Query API endpoints.
* **Status:** Pending.

## 3. Current Blockers / Deferment Reasons
While `maatify/persistence v1.1.0` is now available to provide proper offset pagination mechanics, implementation remains architecturally deferred to prioritize release stabilization. No Composer additions or code modifications may begin under this roadmap without an explicit, approved decision record changing the status to Active.
