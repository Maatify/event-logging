# SecuritySignals Admin Query Rebuild Blueprint

**Status:** Proposed / Runtime Implementation Blocked

## A. Audited Baseline
* **Audit Date:** $(date -u +"%Y-%m-%d")
* **Starting SHA:** 3169947e107df66f61884abb5c95f1dfa621a69b
* **Sources Reviewed:**
  * `src/SecuritySignals/Contract/*`
  * `src/SecuritySignals/DTO/*`
  * `src/SecuritySignals/Infrastructure/Mysql/*`
  * `src/SecuritySignals/Service/*`
  * `tests/Unit/SecuritySignals/*`
* **Distinction:** The `v1.0.0` Runtime primitive interfaces and DTOs are protected contracts. The post-v1 `SecuritySignalsPaginatedQueryService` and its DTOs/interfaces are superseded experiments pending rebuild.

## B. Protected Primitive Contracts
* **Query Interface:** `SecuritySignalsQueryInterface::find(SecuritySignalsQueryDTO $query): array`
* **Request DTO:** `SecuritySignalsQueryDTO` (after, before, actorType, actorId, signalType, severity, requestId, correlationId, cursorOccurredAt, cursorId, limit)
* **View DTO:** `SecuritySignalsViewDTO`
* **Cursor Semantics:** Descending pagination `(occurred_at < :cursor_at OR (occurred_at = :cursor_at AND id < :cursor_id))`.
* **Ordering:** `ORDER BY occurred_at DESC, id DESC`.
* **Limit Behavior:** Clamped with `max(1, $query->limit)`.
* **Hydration Fallbacks:** `0` for missing `id`, `''` for missing `eventId`, `signalType`, `severity`, and `null` for other nullable fields.
* **Exceptions:** `SecuritySignalsStorageException` with "Failed to query SecuritySignals records: " and "Failed to map SecuritySignals row: ".
* **Tests protecting them:** `tests/Unit/SecuritySignals/Repository/SecuritySignalsQueryMysqlRepositoryTest.php`.

## C. Superseded Post-v1 Artifacts
* **Inventory:**
  * `src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php`
  * `src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php`
  * `src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php`
  * `src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php`
* **Historical Origin:** Added as part of a post-v1 pagination experiment.
* **Classification Evidence:** Identified in Phase 1 compatibility inventory as using incorrect cross-domain generic abstractions.
* **Consumers:** No internal examples or dependencies. Host-consumer verification is required before full removal.
* **Retirement Gate:** Atomic retirement of these files is only authorized after the replacement Admin Query path is implemented and tested.

## D. Domain-Specific Findings
* **Schema:** `maa_event_logging_security_signals`. Indexed on time, actor+time, type+time, severity+time, correlation+time, request+time.
* **Filters:** Time bounds, `actorType`, `actorId`, `signalType`, `severity`, `requestId`, `correlationId`.
* **Nullability:** `actor_type`, `signal_type`, `severity` are NOT NULL strings. `actor_id`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent` are nullable.
* **Metadata:** JSON format, limited to 65535 bytes via policy.
* **Policy Behavior:** `SecuritySignalsPolicyInterface` only operates on the write/logging path (`normalizeActorType`, `normalizeSeverity`, `validateMetadataSize`). The primitive read mapping does not use a policy. Therefore, the shared row mapper must be **policy-free**.
* **Failure Semantics:** Best-effort fail-open boundary on write. Admin Query must remain purely read-oriented and not impact this write boundary.
* **Domain Risks:** Metadata must not contain secrets. The Admin Query path must securely expose this data without introducing control-flow dependencies.

## E. Proposed Public Contracts
* **`SecuritySignalsAdminQueryInterface`**
  ```php
  public function getPage(SecuritySignalsAdminQueryRequestDTO $request): SecuritySignalsAdminPageResultDTO;
  ```
* **`SecuritySignalsAdminQueryRequestDTO`**
  Fields mapped from primitive `SecuritySignalsQueryDTO` (with page/perPage replacing cursor).
* **`SecuritySignalsAdminPageResultDTO`**
  Implementing `IteratorAggregate<int, SecuritySignalsViewDTO>` and `JsonSerializable`. Exposing `id`.

## F. Proposed Runtime Internals
* **`@internal SecuritySignalsAdminQueryMysqlRepository`** (implements `SecuritySignalsAdminQueryInterface`)
* **`@internal SecuritySignalsAdminQueryDescriptorBuilder`** (constructs shared filter SQL for persistence)
* **`@internal SecuritySignalsRowMapper`** (shared static mapper extracted from primitive query repository, strictly policy-free)

## G. Exact Filter Contract
* **`after` / `before`:** `occurred_at >= :after`, `occurred_at <= :before` (DATE_ATOM).
* **`actorType`:** `actor_type = :actor_type` (max 32).
* **`actorId`:** `actor_id = :actor_id` (BIGINT).
* **`signalType`:** `signal_type = :signal_type` (max 100).
* **`severity`:** `severity = :severity` (max 16).
* **`requestId`:** `request_id = :request_id` (max 64).
* **`correlationId`:** `correlation_id = :correlation_id` (max 36).

## H. Sorting and Pagination
* **Sort Whitelist:** `occurred_at`.
* **Default Sort:** `occurred_at` DESC.
* **Tie-Breaker:** `id` DESC.
* **Pagination Constraints:** Default 20 per-page, min 1, max 200. Handled exclusively by `maatify/persistence`. Total counts use mandatory constraints; filtered counts use optional constraints.

## I. Exception Architecture
* **Validation Exception:** `SecuritySignalsAdminQueryValidationException` (extends `InvalidRequestMaatifyException`, implements domain marker). Message: "Invalid SecuritySignals query parameters: ..."
* **Execution Exception:** `SecuritySignalsAdminQueryExecutionException` (extends `SystemMaatifyException`, implements domain marker). Message: "Failed to execute SecuritySignals admin query: ..."
* **Storage Exception Translation:** Persistence exceptions from `PdoPaginator` will be caught and translated to `SecuritySignalsAdminQueryExecutionException`.
* **Note:** Primitive query messages remain unchanged.

## J. Exact Future File Inventory
* **Future Additions:**
  * `src/SecuritySignals/Contract/SecuritySignalsAdminQueryInterface.php`
  * `src/SecuritySignals/DTO/SecuritySignalsAdminQueryRequestDTO.php`
  * `src/SecuritySignals/DTO/SecuritySignalsAdminPageResultDTO.php`
  * `src/SecuritySignals/Exception/SecuritySignalsAdminQueryValidationException.php`
  * `src/SecuritySignals/Exception/SecuritySignalsAdminQueryExecutionException.php`
  * `src/SecuritySignals/Infrastructure/Mysql/Pagination/SecuritySignalsAdminQueryMysqlRepository.php`
  * `src/SecuritySignals/Infrastructure/Mysql/Pagination/SecuritySignalsAdminQueryDescriptorBuilder.php`
  * `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsRowMapper.php`
* **Future Deletions:**
  * `SecuritySignalsPaginatedQueryInterface.php`
  * `SecuritySignalsQueryCursorDTO.php`
  * `SecuritySignalsQueryPageDTO.php`
  * `SecuritySignalsPaginatedQueryService.php`
  * (And associated tests)
* **Explicitly Out-of-Scope:**
  * `SecuritySignalsQueryMysqlRepository` (aside from using shared mapper and fixing cursor placeholder).
  * `SecuritySignalsRecorder`, `SecuritySignalsLoggerMysqlRepository` (write paths).
  * `SecuritySignalsDefaultPolicy`.

## K. Test Plan
* **Unit Tests:** Ensure DTOs, mappers, and descriptor builders properly translate inputs without side effects.
* **Regression Tests:** Guarantee that the extracted `SecuritySignalsRowMapper` perfectly mimics the existing repository hydration behavior (including all fallbacks and null handling).
* **Real MySQL Integration Tests:**
  * Must use real MySQL (no SQLite fallback).
  * Verify `getPage()` alignment with `find()` using native prepared statements.
  * Prove zero-row handling, pagination offset clamping, and total/filtered count alignment.
* **Primitive Cursor Correction:** Test regression to prove the `:cursor_at_before` and `:cursor_at_equal` placeholders solve the native PDO binding issue without changing behavior.

## L. Blockers and Unresolved Decisions
* **Primitive Placeholder Correction:** Does the Owner approve applying the native PDO placeholder fix to `SecuritySignalsQueryMysqlRepository`?
  ```sql
  (occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))
  ```
* **Host Consumer Verification:** Can the Owner confirm that no host applications strictly depend on `SecuritySignalsPaginatedQueryService` before its atomic deletion?

## M. Runtime Authorization Gate
No SecuritySignals Runtime implementation is authorized by this blueprint.
Proceeding to implementation requires:
1. Review of this PR.
2. Owner approval.
3. A separate Runtime execution task assigned to Codex.
