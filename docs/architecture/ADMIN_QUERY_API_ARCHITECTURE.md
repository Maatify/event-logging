# Admin Query API Architecture

**Status:** Approved Architecture / Implementation Deferred
**Phase:** 0 Complete / Runtime Implementation Blocked

## 1. Overview
This document defines the canonical future architecture for the Admin Query API within the `maatify/event-logging` package. It replaces all previous roadmap assumptions and clearly separates the **Primitive Read/Query Runtime** (currently active) from the future **Admin Query API**.

Although `maatify/persistence v1.1.0` is now available and provides the necessary standardized pagination mechanics, the implementation of the Admin Query API remains strictly **deferred**. No code, runtime, public API, or Composer changes will be made until explicit Owner approval is granted.

## 2. Separation of Runtimes

### 2.1 Primitive Read/Query Runtime (Current Active Architecture)
The current runtime relies on two distinct layers, neither of which will be removed or modified by this documentation phase:

1. **Primitive Cursor Query Repositories:** These exist across all six logging domains and provide the fundamental reading capabilities.
2. **Paginated Query Wrapper Experiment:** The additional `*PaginatedQueryInterface`, cursor/page DTOs, and `*PaginatedQueryService` are an experiment currently present only in four domains: `AuditTrail`, `AuthoritativeAudit`, `SecuritySignals`, and `BehaviorTrace`.

**Critical Boundary:**
This old paginated wrapper experiment will **NOT** be extended to `DiagnosticsTelemetry` or `DeliveryOperations` as the future Admin Query API design.

### 2.2 Future Admin Query API (Target Architecture)
The target Admin Query API represents an entirely separate, robust execution path tailored for traditional admin grid layouts, filtering, sorting, and traditional offset-based pagination. It relies directly on `maatify/persistence` for generic mechanics.

## 3. Strict Boundary Responsibilities

### 3.1 Event Logging Package (`maatify/event-logging`)
When implemented, this package will strictly own:
- Mandatory domain constraints.
- Security, ownership, tenant and visibility constraints.
- Search and filter construction.
- Domain JOINs and selected columns where applicable.
- Trusted SQL and matching parameters.
- Approved domain sort keys and their trusted column mappings.
- Semantic alignment between count and data queries.
- Row mapping into domain DTOs.
- Preservation of package-owned response contracts through a thin adapter.

### 3.2 Pagination Owner (`maatify/persistence v1.1.0+`)
The external `maatify/persistence` package exclusively owns all generic pagination mechanics:
- Page/per-page normalization.
- Total and filtered count execution.
- Offset calculation.
- Deterministic sorting execution.
- Sort whitelist enforcement.
- Tie-breaker behavior.
- Limit/offset handling.
- Mapper invocation.
- Canonical pagination metadata.

### 3.3 Host Application
The host application consuming the package retains absolute control over integration:
- **HTTP / UI:** Controllers, routes, and user interface screens.
- **Security / Authorization:** Permissions checks and user validation.
- **Resolution:** Actor and target name hydration (e.g., resolving `userId: 5` to "John Doe").
- **Localization:** Translating internal keys to human-readable strings.
- **Output Mapping:** Converting the returned pagination DTOs to standard HTTP responses or exports.

## 4. Current Contract Precedence
- The [EVENT_LOGGING_PACKAGE_REFERENCE.md](../../EVENT_LOGGING_PACKAGE_REFERENCE.md) remains the canonical current stable Runtime and public API contract.
- This future architecture document does not change the current public API, current Runtime behavior, Composer dependencies, or compatibility guarantees.
- Until a separately approved implementation is released, the current primitive read/query rules defined in the [EVENT_LOGGING_PACKAGE_REFERENCE.md](../../EVENT_LOGGING_PACKAGE_REFERENCE.md) and [PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md](PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md) remain authoritative. The [PACKAGE_BUILDING_STANDARD.md](../standards/PACKAGE_BUILDING_STANDARD.md) acts as the governing standards reference.
- The future Admin Query API architecture becomes Runtime truth only after a separately approved implementation and release update. For more details on deferred scope see [DEFERRED_SCOPE.md](DEFERRED_SCOPE.md) and the [ADMIN_QUERY_API_ROADMAP.md](../roadmap/ADMIN_QUERY_API_ROADMAP.md).

## 5. Absolute Prohibitions
To ensure `maatify/event-logging` remains a purely domain-isolated package, the following are strictly prohibited in the future implementation:
- **Generic Admin Repository:** No single overarching repository for all domains.
- **Generic Cross-Domain Queries:** Queries must not cross domain boundaries.
- **HTTP/UI Code:** No controllers, routes, or dashboard code inside this package.
- **Copied Pagination Mechanics:** Do not copy or redefine the persistence algorithms from `maatify/persistence`.

## 6. Implementation Gate
Implementation cannot begin simply because the dependency exists. Proceeding requires a separate architectural approval and activation of Phase 2.
