# Admin Query API Architecture

**Status:** Approved Architecture / Implementation Deferred
**Phase:** 0 In Progress / Runtime Implementation Blocked

## 1. Overview
This document defines the canonical future architecture for the Admin Query API within the `maatify/event-logging` package. It replaces all previous roadmap assumptions and clearly separates the **Primitive Read/Query Runtime** (currently active) from the future **Admin Query API**.

Although `maatify/persistence v1.1.0` is now available and provides the necessary standardized pagination mechanics, the implementation of the Admin Query API remains strictly **deferred**. No code, runtime, public API, or Composer changes will be made until explicit Owner approval is granted.

## 2. Separation of Runtimes

### 2.1 Primitive Read/Query Runtime (Current Active Architecture)
The currently implemented cursor-based pagination is considered the **Primitive Read/Query Runtime**.
- It is already fully implemented and actively operating in domains like `AuditTrail` and `AuthoritativeAudit`.
- It remains completely unchanged and its current documentation correctly reflects its behavior.
- **Critical:** This cursor-based pattern is independent and will **NOT** be generalized or rolled out to additional domains as a precursor to the Admin Query API.

### 2.2 Future Admin Query API (Target Architecture)
The target Admin Query API represents an entirely separate, robust execution path tailored for traditional admin grid layouts, filtering, sorting, and traditional offset-based pagination. It relies directly on `maatify/persistence` for generic mechanics.

## 3. Strict Boundary Responsibilities

### 3.1 Event Logging Package (`maatify/event-logging`)
When implemented, this package will strictly own:
- **Domain-specific Query Contracts:** E.g., `AuditTrailAdminQueryInterface`.
- **Domain Filters:** Concrete filtering criteria tailored to individual domains.
- **Domain-owned SQL:** Writing specific `SELECT`, `WHERE`, and `ORDER BY` clauses natively.
- **Security & Visibility Constraints:** Ensuring domain rules apply at the query level.
- **Row Mapping:** Converting returned database rows into strict, domain-specific View DTOs.
- **Thin Integration Adapter:** A minimal bridging adapter connecting the native query logic to the external persistence layer.

### 3.2 Pagination Owner (`maatify/persistence v1.1.0+`)
The external `maatify/persistence` package exclusively owns all generic pagination mechanics:
- **Normalization:** Validating page and per-page parameters.
- **Total & Filtered Execution:** Computing total items and filtered counts.
- **Offset Calculation:** Resolving safe database offset integers.
- **Sort Execution:** Applying safe sort whitelists and deterministic tie-breakers.
- **Result Construction:** Returning canonical pagination metadata alongside the actual results.

### 3.3 Host Application
The host application consuming the package retains absolute control over integration:
- **HTTP / UI:** Controllers, routes, and user interface screens.
- **Security / Authorization:** Permissions checks and user validation.
- **Resolution:** Actor and target name hydration (e.g., resolving `userId: 5` to "John Doe").
- **Localization:** Translating internal keys to human-readable strings.
- **Output Mapping:** Converting the returned pagination DTOs to standard HTTP responses or exports.

## 4. Absolute Prohibitions

To ensure `maatify/event-logging` remains a purely domain-isolated package, the following are strictly prohibited in the future implementation:
- **Generic Admin Repository:** No single overarching repository for all domains.
- **Generic Cross-Domain Queries:** Queries must not cross domain boundaries.
- **HTTP/UI Code:** No controllers, routes, or dashboard code inside this package.
- **Copied Pagination Mechanics:** No duplication or mimicking of the offset pagination calculations found in `maatify/persistence`.

## 5. Implementation Gate
Implementation cannot begin simply because the dependency exists. Proceeding requires a separate architectural approval and activation of Phase 2.
