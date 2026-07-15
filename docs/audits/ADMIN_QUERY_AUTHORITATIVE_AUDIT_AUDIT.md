# Authoritative Audit: Admin Query API Remediation Audit

**Target:** `AuthoritativeAudit` domain
**Purpose:** Rebuild post-v1 pagination experiment into Admin Query API architecture.

## 1. Protected `v1.0.0` Contracts (Do Not Modify)

**Recording:**
- `AuthoritativeAuditRecorder` and `AuthoritativeAuditDefaultPolicy`.
- `AuthoritativeAuditOutboxWriterInterface`.
- Strict **fail-closed** behavior. Exceptions must not be swallowed; they must propagate to the caller to abort the transaction.

**Primitive Read/Query:**
- `AuthoritativeAuditQueryInterface::find()`
- `AuthoritativeAuditQueryDTO` (base filters: `actorType`, `actorId`, `targetType`, `targetId`, `action`, `correlationId`, `after`, `before`).
- `AuthoritativeAuditViewDTO`.
- `AuthoritativeAuditQueryMysqlRepository`.

## 2. Post-v1.0 Pagination Artifacts (To Be Deleted/Rebuilt)

The following artifacts belong to the abandoned cursor-based pagination experiment and must be **deleted** when the Admin Query API is introduced:
- `AuthoritativeAuditPaginatedQueryInterface`
- `AuthoritativeAuditPaginatedQueryService`
- `AuthoritativeAuditQueryPageDTO`
- `AuthoritativeAuditQueryCursorDTO`
- Cursor fields (`cursorOccurredAt`, `cursorId`, `limit`) inside `AuthoritativeAuditQueryDTO`.

*Note: These should be replaced by a separate `AuthoritativeAuditAdminQueryInterface` using `maatify/persistence` for limit/offset pagination, and distinct DTOs for the query and page results.*

## 3. Storage and Boundary Review

**Schema & Indexes:**
- The domain maintains two tables: `maa_event_logging_authoritative_audit_outbox` (write truth) and `maa_event_logging_authoritative_audit_log` (read materialization).
- Admin queries strictly read from the `_log` table. The outbox is **not** queried by the host for listing.

**Filters:**
- Standard equality filters are implemented (`actor_type`, `actor_id`, `target_type`, `target_id`, `action`, `correlation_id`).
- Date filters (`after`, `before`) map to `occurred_at`.

**Hydration & Exceptions:**
- `AuthoritativeAuditStorageException` is used correctly extending `SystemMaatifyException` (Code: `DATABASE_CONNECTION_FAILED`).
- Payload hydration safely handles corrupt JSON (returns `null`), protecting read availability.

## 4. Testing Gaps

- **Integration:** Strict MySQL integration tests exist (`AuthoritativeAuditRepositoryTest`), covering roundtrips and JSON corrupt handling.
- **Unit:** Extensive unit tests exist for commands, DTOs, recorders, and services.
- **Regression:** A `tests/Regression/AuthoritativeAudit` directory is **missing**. Regression tests proving that primitive behavior remains identical after the rebuild will be required.

## 5. Host Integration Wrapper Usages

- Package search: Currently, only internal services (`AuthoritativeAuditPaginatedQueryService`) and tests reference the post-v1 pagination interfaces.
- Host repository search: **Must** be performed before or during the PR to ensure no host is relying on `AuthoritativeAuditPaginatedQueryInterface`. If found, they must be migrated to the new API synchronously.

## 6. Open Decisions Required Before Blueprint

1. **Sort Whitelist:** What are the permitted sort columns for the Admin Query API (e.g., `occurred_at`, `id`)?
2. **Actor Search Performance:** Querying `actorId` without `actorType` is permitted in `SecuritySignals`, but we need to confirm if the same performance implication is accepted here.
3. **Data Mapping:** Will the Admin API map to `AuthoritativeAuditViewDTO` or require a distinct Admin DTO (as done in other rebuilds if needed)? Since this domain already exposes `id` in `AuthoritativeAuditViewDTO`, it might be reusable.
