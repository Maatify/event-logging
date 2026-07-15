# Authoritative Audit: Admin Query API Remediation Audit

**Target:** `AuthoritativeAudit` domain
**Audited SHA:** `c8a121768ddfcec01793b63f46f7e7af37d951a9`
**Audit Date:** 2024-07-15
**Purpose:** Rebuild post-v1 pagination experiment into Admin Query API architecture.

## 1. Protected `v1.0.0` Contracts (Do Not Modify)

The following `v1.0.0` baseline contracts are strictly protected and must remain unchanged:

**Recording / Outbox:**
- `AuthoritativeAuditRecorder::record()` and `AuthoritativeAuditDefaultPolicy::validatePayload()`.
- `AuthoritativeAuditOutboxWriterInterface::write(AuthoritativeAuditOutboxWriteDTO $dto)`.
- Strict **fail-closed** behavior: Exceptions during write must not be swallowed; they propagate to the caller to abort the transaction.
- The outbox (`maa_event_logging_authoritative_audit_outbox`) is the **transactional source of truth**.

**Primitive Read/Query:**
- `AuthoritativeAuditQueryInterface::find(AuthoritativeAuditQueryDTO $query)`.
- `AuthoritativeAuditQueryDTO` **in its entirety**, including the primitive cursor fields (`cursorOccurredAt`, `cursorId`, `limit`). This is a protected `v1.0.0` contract.
- Primitive cursor behavior, limit normalization, and descending ordering (`occurred_at DESC, id DESC`) must remain unchanged.
- `AuthoritativeAuditViewDTO`.
- `AuthoritativeAuditQueryMysqlRepository` and its native PDO parameter usage.
- Payload hydration fallbacks (mapping corrupt JSON strictly to `null`).
- `AuthoritativeAuditStorageException` mappings.

## 2. Post-v1.0 Pagination Artifacts (To Be Superseded and Deleted)

The exact superseded artifacts to be replaced and deleted from the package:
- `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`
- `src/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryService.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTO.php`
- The three corresponding unit test files: `AuthoritativeAuditPaginatedQueryServiceTest.php`, `AuthoritativeAuditQueryPageDTOTest.php`, and `AuthoritativeAuditQueryCursorDTOTest.php`.

## 3. Storage and Boundary Semantics

- The materialized log (`maa_event_logging_authoritative_audit_log`) is **not authoritative** and is written *only* by the outbox consumer.
- **Admin listings strictly read from the log**, never from the outbox.
- No schema changes are required or authorized.
- No transaction ownership rules apply to the read layers.
- Exact Primitive Signatures, Defaults & Serialization: Date filters use `DateTimeImmutable` mapped to `DATE_ATOM` in JSON. Default limit is 50. Empty queries filter nothing. Date bounds (`after`, `before`) translate to `>=` and `<=`.
- Exceptions thrown extend `SystemMaatifyException` with `DATABASE_CONNECTION_FAILED`.

## 4. Testing & Remediation Gaps

The implementation must address the following coverage and implementation gaps:

- **MySQL Parameter Gap:** Primitive MySQL query currently reuses `:cursor_at` under native PDO, which violates native prepared statement rules. Needs unique placeholders (e.g., `:cursor_at_before`, `:cursor_at_equal`).
- **Corrupt JSON Tests:** The current integration test for corrupt JSON (`testCorruptJsonMapsToNullSafely`) might skip on strict databases (MySQL 8+). Needs verification if it can be reliably tested.
- **Matrix Gaps:** A full Unit, Regression, and strict MySQL Integration test matrix is required for the new Admin Query API.
- **Admin Semantics:** Requires explicit filtered-count vs. data semantic alignment, precise pagination normalization (page/per_page), limit clamping, tie-breaker handling, null-column handling, and independent filter evaluation.

## 5. Host Integration Wrapper Usages

- **Package Search:** Completed. The superseded interfaces are strictly isolated to `src/AuthoritativeAudit/Service` and their tests.
- **Host Repositories Search:** *Access Gap*. The sandbox does not have access to private host repositories (e.g., Athar, EP4N) to confirm usage. Host teams must migrate any usages of `AuthoritativeAuditPaginatedQueryInterface` to the new Admin Query API synchronously before or during the PR.

## 6. Open Decisions Required Before Blueprint

The following decisions must be made before drafting the Admin Query API blueprint:

1. **Sort Whitelist:** Which specific columns are allowed for Admin Query API sorting? (e.g., `occurred_at` DESC, `id` DESC).
2. **Actor/Target Type-ID Semantics:** Is querying `actorId` or `targetId` without their corresponding `Type` permitted? (In `SecuritySignals`, independent actor searches are permitted despite index implications; need to confirm this for AuthoritativeAudit).
3. **Mapper & Result DTO:** Will the Admin API reuse `AuthoritativeAuditViewDTO` (since it already exposes the `id` field) or map to a distinct Admin DTO?
4. **Validation Bounds:** What are the exact maximum limits for `per_page` in the Admin Query API?
5. **Exception Boundaries:** Differentiating strictly between invalid arguments (e.g., bad sort column) vs. execution failures (database connectivity).
6. **Retirement Set Confirmation:** Explicit sign-off on the exact list of files to be deleted (the post-v1 pagination artifacts).
