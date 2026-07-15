# Authoritative Audit: Admin Query API Remediation Audit

**Target:** `AuthoritativeAudit` domain
**Audited SHA:** `c8a121768ddfcec01793b63f46f7e7af37d951a9`
**Audit Date:** 2026-07-15
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
- **Observable primitive contract:** `AuthoritativeAuditQueryDTO` in its entirety, including the primitive cursor fields (`cursorOccurredAt`, `cursorId`, `limit`), is a protected `v1.0.0` contract.
  *(Note: Placeholder naming for native PDO, like replacing reused `:cursor_at`, is an internal detail that can be corrected behavior-preservingly without breaking the observable contract).*
- **Exact primitive constructor signature:** `public function __construct(public ?\DateTimeImmutable $after = null, public ?\DateTimeImmutable $before = null, public ?string $actorType = null, public ?int $actorId = null, public ?string $targetType = null, public ?int $targetId = null, public ?string $action = null, public ?string $correlationId = null, public ?\DateTimeImmutable $cursorOccurredAt = null, public ?int $cursorId = null, public int $limit = 50)`
- **Defaults:** Filters default to `null`. Limit defaults to `50`.
- **Serialization Key Order:** `after`, `before`, `actorType`, `actorId`, `targetType`, `targetId`, `action`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`.
- **Cursor Activation:** Cursor is activated only if *both* `cursorOccurredAt` AND `cursorId` are strictly not null.
- **Limit Normalization:** `max(1, $query->limit)` is enforced at the repository level.
- **Ordering:** Descending order is strictly `occurred_at DESC, id DESC`.
- **Hydration:** Payload hydration fallbacks (mapping corrupt JSON strictly to `null`).
- **Exception Messages:**
  - Query failure: `'Failed to query AuthoritativeAudit records: ' . $e->getMessage()`
  - Hydration failure: `'Failed to map AuthoritativeAudit row: ' . $e->getMessage()`

## 2. Post-v1.0 Pagination Artifacts (To Be Superseded and Deleted)

The exact superseded artifacts to be replaced and deleted from the package:
- `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`
- `src/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryService.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTO.php`
- `tests/Unit/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryServiceTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTOTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTOTest.php`

## 3. Storage and Boundary Semantics

- The materialized log (`maa_event_logging_authoritative_audit_log`) is **not authoritative** and is written *only* by the outbox consumer.
- **Admin listings strictly read from the log**, never from the outbox.
- No schema changes are required or authorized.
- Read repositories must not start, commit, or rollback transactions. They must maintain strict caller-owned transaction semantics.

## 4. Testing & Remediation Gaps

The implementation must address the following coverage and implementation gaps:

- **MySQL Parameter Gap:** Primitive MySQL query currently reuses `:cursor_at` under native PDO. Must be corrected behavior-preservingly.
- **Corrupt JSON Tests:** The current integration test for corrupt JSON might skip on strict databases (MySQL 8+). Needs verification if it can be reliably tested.
- **Matrix Gaps:** A full Unit, Regression, and strict MySQL Integration test matrix is required for the new Admin Query API.
- **Admin Semantics:** Requires explicit filtered-count vs. data semantic alignment, precise pagination normalization (page/per_page), limit clamping, tie-breaker handling, null-column handling, and independent filter evaluation.

## 5. Host Integration Wrapper Usages

- **Package Search:** Completed. The superseded interfaces are strictly isolated to `src/AuthoritativeAudit/Service` and their tests.
- **Host Repositories Search (Exact Repositories):**
  - `Maatify/athar-admin`: searched; no references found.
  - `Maatify/athar-user`: searched; no references found.
  - `Maatify/ep4n-2020`: searched; no references found.
  - *Note:* The search included `AuthoritativeAuditPaginatedQueryInterface`, `AuthoritativeAuditPaginatedQueryService`, `AuthoritativeAuditQueryPageDTO`, and `AuthoritativeAuditQueryCursorDTO`.

## 6. Open Decisions Required Before Blueprint

The following decisions must be made before drafting the Admin Query API blueprint:

1. **Sort Whitelist:** Which specific columns are allowed for Admin Query API sorting?
2. **Actor/Target Type-ID Semantics:** Is querying `actorId` or `targetId` without their corresponding `Type` permitted?
3. **Mapper & Result DTO:** Will the Admin API reuse `AuthoritativeAuditViewDTO` or map to a distinct Admin DTO?
4. **Validation Bounds:** What are the exact maximum limits for `per_page` in the Admin Query API?
5. **Exception Boundaries:**
   - Invalid Admin request → `AuthoritativeAuditAdminQueryInvalidArgumentException`
   - `InvalidPaginationConfigurationException` or `InvalidPaginationQueryException` → `AuthoritativeAuditAdminQueryExecutionException`
   - `PaginationExecutionException` or `PDOException` → `AuthoritativeAuditStorageException`
   - mapper/hydration `Throwable` → `AuthoritativeAuditStorageException`
   - *Constraint:* Must preserve exact message prefixes and the previous throwable (`$e`). Any `AuthoritativeAuditStorageException` originating from the mapper must be passed through without re-wrapping.
6. **Retirement Set Confirmation:** Explicit sign-off on the exact list of 7 files to be deleted.
