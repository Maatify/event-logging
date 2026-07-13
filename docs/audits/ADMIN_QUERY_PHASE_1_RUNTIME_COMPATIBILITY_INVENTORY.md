# Admin Query API Phase 1 Runtime Compatibility Inventory

**Status:** Active — Current Phase 1 Runtime Inventory
**Verdict:** READY FOR PHASE 2 DESIGN APPROVAL

## 1. Environment Details
*   **event-logging SHA:** e23acf996bf08288ce802358f7e347b69955fdbe
*   **maatify/persistence Tag/Commit:** v1.1.0 (5850dbea48e571eae644f1f490e137c8a4202d9d)
*   **Current Composer Dependency State:** `maatify/persistence` is **NOT** present in `composer.json` or `composer.lock`. The current state strictly adheres to the Phase 0 deferred scope.

## 2. Six-Domain Comparison Matrix

| Domain | Primitive Query Interface | View DTO / Event DTO | Paginated Wrapper | SQL Table |
| :--- | :--- | :--- | :--- | :--- |
| **AuthoritativeAudit** | `AuthoritativeAuditQueryInterface` | `AuthoritativeAuditViewDTO` | `AuthoritativeAuditPaginatedQueryInterface` | `maa_event_logging_authoritative_audit_log` |
| **AuditTrail** | `AuditTrailQueryInterface` | `AuditTrailViewDTO` | `AuditTrailPaginatedQueryInterface` | `maa_event_logging_audit_trail` |
| **SecuritySignals** | `SecuritySignalsQueryInterface` | `SecuritySignalsViewDTO` | `SecuritySignalsPaginatedQueryInterface` | `maa_event_logging_security_signals` |
| **BehaviorTrace** | `BehaviorTraceQueryInterface` | `BehaviorTraceEventDTO` (Raw) | `BehaviorTracePaginatedQueryInterface` | `maa_event_logging_behavior_trace` |
| **DiagnosticsTelemetry** | `DiagnosticsTelemetryQueryInterface` | `DiagnosticsTelemetryEventDTO` (Raw) | None | `maa_event_logging_diagnostics_telemetry` |
| **DeliveryOperations** | `DeliveryOperationsQueryInterface` | `DeliveryOperationsViewDTO` | None | `maa_event_logging_delivery_operations` |

## 3. Primitive Query Inventory
Across all domains, the primitive query interfaces support filtering by various fields, cursor-based pagination (`cursorOccurredAt`, `cursorId`), and a `limit` parameter.
The MySQL repositories execute standard `SELECT * FROM table WHERE ... ORDER BY occurred_at DESC, id DESC LIMIT ...` and manually hydrate results into DTOs while safely ignoring or mapping invalid `json` entries.

## 4. Paginated-Wrapper Inventory
The following domains contain a `*PaginatedQueryInterface`, `*QueryCursorDTO`, `*QueryPageDTO`, and `*PaginatedQueryService`:
*   AuthoritativeAudit
*   AuditTrail
*   SecuritySignals
*   BehaviorTrace

DiagnosticsTelemetry and DeliveryOperations do not implement these wrappers, strictly adhering to the architectural restriction that this experiment will not be generalized.

## 5. Per-Domain SQL and Index Readiness
All tables share a similar schema foundation:
*   `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
*   `occurred_at DATETIME(6) NOT NULL`
*   `INDEX idx_*_time (occurred_at, id)`

This `(occurred_at DESC, id DESC)` composite index provides stable, deterministic sorting which is fully compatible with offset sorting.

## 6. Per-Domain Filter and Sorting Readiness
Filters typically apply to indexed columns (e.g., `actor_type`, `actor_id`, `occurred_at`, `action`, `event_key`, `channel`). Deterministic offset sorting is possible using `occurred_at` as the primary sort and `id` as the tie-breaker.
The schema allows for total-count and filtered-count SQL to remain semantically aligned.

## 7. DTO and Hydration Reuse Analysis
The existing View and Event DTOs can safely be reused. The primitive query interfaces and DTOs *must remain untouched*. The future Admin path will require new DTOs (e.g., `*AdminListDTO` or leveraging `maatify/persistence`'s `PageResult`). Thin adapters can map the current query parameters to the new API.

## 8. Backward-Compatibility Constraints
*   The Primitive Query API must not be modified or removed.
*   The `PaginatedQueryService` experiments must remain intact for domains that already have them until a migration path is defined post-POC.

## 9. Persistence Integration Mapping
**Available in `maatify/persistence v1.1.0`:**
*   `Maatify\Persistence\Pdo\Pagination\PageRequest`
*   `Maatify\Persistence\Pdo\Pagination\PageResult`
*   `Maatify\Persistence\Pdo\Pagination\PaginationConfig`
*   `Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor`
*   `Maatify\Persistence\Pdo\Pagination\PdoPaginator`

**What event-logging must supply:**
*   Domain-specific SQL queries (Total, Filtered, Data).
*   Parameter binding definitions.
*   Row mappers (reusing existing hydration logic).
*   Domain-owned adapters to wrap the `PdoPaginator`.

## 10. Test Coverage and Future Test-Gap Inventory
The primitive implementations and paginated wrappers have coverage. The Admin Query API will require new integration tests covering:
*   Pagination edge cases (empty pages, out of bounds).
*   Filter count vs total count assertions.
*   Sort ordering correctness matching the paginator configuration.

## 11. Blockers and Non-Blocking Gaps
**Blockers:** None for Phase 2 entry.
**Non-Blocking Gaps:** Lack of defined reporting summary contracts (Deferred to Phase 4).

## 12. Recommended Single-Domain POC Candidate
**Candidate:** `AuditTrail`
**Evidence-based reason:** AuditTrail represents a mature read-heavy domain with well-defined filtering dimensions (actor and event_key). It already implements the paginated wrapper experiment, proving demand for pagination, and its schema complexity is moderate (including entity and subject dimensions) making it highly representative for validating the `PdoPaginator` integration without the strict fail-closed governance complexities of `AuthoritativeAudit`.

## 13. Rejected POC Candidates
*   **AuthoritativeAudit:** Rejected because it is the most governance-critical, fail-closed domain. Introducing an experimental POC here carries higher risk.
*   **SecuritySignals:** Rejected as it is best-effort and non-authoritative.
*   **BehaviorTrace, DiagnosticsTelemetry, DeliveryOperations:** Rejected because they either lack the wrapper experiment or represent less read-heavy operational data.

## 14. Exact Phase 2 Entry Conditions
1.  Owner Approval for Phase 2 implementation.
2.  Add `maatify/persistence ^1.1.0` to `composer.json`.
3.  Implement the Admin Query API in `AuditTrail` only.

## 15. Final Verdict
READY FOR PHASE 2 DESIGN APPROVAL