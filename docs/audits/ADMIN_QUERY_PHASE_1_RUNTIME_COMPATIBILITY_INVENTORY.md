# Admin Query API Phase 1 Runtime Compatibility Inventory

**Status:** Active — Current Phase 1 Runtime Inventory
**Verdict:** READY FOR PHASE 2 DESIGN APPROVAL

## 1. Phase 1 Scope & Constraints
This audit strictly validates current Runtime compatibility for a future Phase 2 Admin Query API implementation relying on `maatify/persistence v1.1.0`. No runtime changes, new dependencies, or implementations are authorized in Phase 1.

**Environment Check:**
- **Audit event-logging SHA:** e23acf996bf08288ce802358f7e347b69955fdbe
- **Audited maatify/persistence tag/commit:** v1.1.0 (5850dbea48e571eae644f1f490e137c8a4202d9d)
- **Composer State:** Confirmed `maatify/persistence` is not in `composer.json` or `composer.lock`.

## 2. Six-Domain Runtime Inventory

### AuthoritativeAudit
- **Primitive query interface:** `src/AuthoritativeAudit/Contract/AuthoritativeAuditQueryInterface.php`
- **Method signature:** `public function find(AuthoritativeAuditQueryDTO $query): array;`
- **Query DTO:** `AuthoritativeAuditQueryDTO` (Fields: `after`, `before`, `actorType`, `actorId`, `targetType`, `targetId`, `action`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit` default 50).
- **View DTO:** `AuthoritativeAuditViewDTO` (includes `id`, `eventId`, `actorType`, `actorId`, `action`, `targetType`, `targetId`, `ipAddress`, `userAgent`, `correlationId`, `changes`, `occurredAt`).
- **MySQL repository:** `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditQueryMysqlRepository.php`
- **Table name:** `maa_event_logging_authoritative_audit_log`
- **Existing filters:** `actorType`, `actorId`, `targetType`, `targetId`, `action`, `correlationId`, `after`, `before`.
- **Cursor fields:** `cursorOccurredAt`, `cursorId`.
- **Limit behavior:** `limit` capped at max(1, query->limit). Default is 50.
- **Existing ordering:** `ORDER BY occurred_at DESC, id DESC`
- **Exception behavior:** Throws `AuthoritativeAuditStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. Safely handles missing/corrupt JSON by mapping to null.
- **Tests:** `tests/Integration/AuthoritativeAudit/AuthoritativeAuditRepositoryTest.php` covers write, query, cursor pagination. Unit/Regression coverage available.
- **Examples:** None directly demonstrating pagination.

### AuditTrail
- **Primitive query interface:** `src/AuditTrail/Contract/AuditTrailQueryInterface.php`
- **Method signature:** `public function find(AuditTrailQueryDTO $query): array;`
- **Query DTO:** `AuditTrailQueryDTO` (Fields: `after`, `before`, `actorType`, `actorId`, `eventKey`, `entityType`, `entityId`, `subjectType`, `subjectId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit` default 50).
- **View DTO:** `AuditTrailViewDTO` (includes `id`, `eventId`, `actorType`, `actorId`, `eventKey`, `entityType`, `entityId`, `subjectType`, `subjectId`, `referrerRouteName`, `referrerPath`, `referrerHost`, `correlationId`, `requestId`, `routeName`, `ipAddress`, `userAgent`, `metadata`, `occurredAt`).
- **MySQL repository:** `src/AuditTrail/Infrastructure/Mysql/AuditTrailQueryMysqlRepository.php`
- **Table name:** `maa_event_logging_audit_trail`
- **Existing filters:** `actorType`, `actorId`, `eventKey`, `entityType`, `entityId`, `subjectType`, `subjectId`, `correlationId`, `after`, `before`.
- **Cursor fields:** `cursorOccurredAt`, `cursorId`.
- **Limit behavior:** Default 50, strictly positive.
- **Existing ordering:** `ORDER BY occurred_at DESC, id DESC`
- **Exception behavior:** Throws `AuditTrailStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. JSON metadata safely decoded.
- **Tests:** `tests/Integration/AuditTrail/AuditTrailRepositoryTest.php` covers write/query roundtrip and cursor pagination.
- **Examples:** `examples/11-audit-trail-paginated.php`.

### SecuritySignals
- **Primitive query interface:** `src/SecuritySignals/Contract/SecuritySignalsQueryInterface.php`
- **Method signature:** `public function find(SecuritySignalsQueryDTO $query): array;`
- **Query DTO:** `SecuritySignalsQueryDTO` (Fields: `after`, `before`, `actorType`, `actorId`, `signalType`, `severity`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit` default 50).
- **View DTO:** `SecuritySignalsViewDTO`
- **MySQL repository:** `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsQueryMysqlRepository.php`
- **Table name:** `maa_event_logging_security_signals`
- **Existing filters:** `actorType`, `actorId`, `signalType`, `severity`, `correlationId`, `after`, `before`.
- **Cursor fields:** `cursorOccurredAt`, `cursorId`.
- **Limit behavior:** Default 50.
- **Existing ordering:** `ORDER BY occurred_at DESC, id DESC`
- **Exception behavior:** Throws `SecuritySignalsStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`.
- **Tests:** `tests/Integration/SecuritySignals/SecuritySignalsRepositoryTest.php`
- **Examples:** None directly demonstrating pagination.

### BehaviorTrace
- **Primitive query interface:** `src/BehaviorTrace/Contract/BehaviorTraceQueryInterface.php`
- **Method signature:** Exposes both `public function find(BehaviorTraceQueryDTO $query): array;` and legacy `public function read(?BehaviorTraceCursorDTO $cursor = null, int $limit = 100): array;`
- **Query DTO:** `BehaviorTraceQueryDTO`
- **View DTO:** `BehaviorTraceEventDTO` (Raw Event DTO).
- **MySQL repository:** `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceQueryMysqlRepository.php`
- **Table name:** `maa_event_logging_behavior_trace`
- **Existing filters:** `actorType`, `actorId`, `action`, `entityType`, `entityId`, `correlationId`, `after`, `before`.
- **Cursor fields:** `cursorOccurredAt`, `cursorId`.
- **Limit behavior:** Default 50.
- **Existing ordering:** `ORDER BY occurred_at DESC, id DESC`
- **Exception behavior:** Throws `BehaviorTraceStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`.
- **Tests:** `tests/Integration/BehaviorTrace/BehaviorTraceRepositoryTest.php`
- **Examples:** None directly demonstrating pagination.

### DiagnosticsTelemetry
- **Primitive query interface:** `src/DiagnosticsTelemetry/Contract/DiagnosticsTelemetryQueryInterface.php`
- **Method signature:** Exposes both `public function find(DiagnosticsTelemetryQueryDTO $query): array;` and legacy `public function read(?DiagnosticsTelemetryCursorDTO $cursor = null, int $limit = 100): array;`
- **Query DTO:** `DiagnosticsTelemetryQueryDTO`
- **View DTO:** `DiagnosticsTelemetryEventDTO` (Raw Event DTO).
- **MySQL repository:** `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryQueryMysqlRepository.php`
- **Table name:** `maa_event_logging_diagnostics_telemetry`
- **Existing filters:** `actorType`, `actorId`, `eventKey`, `severity`, `requestId`, `correlationId`, `after`, `before`.
- **Cursor fields:** `cursorOccurredAt`, `cursorId`.
- **Limit behavior:** Default 50.
- **Existing ordering:** `ORDER BY occurred_at DESC, id DESC`
- **Exception behavior:** Throws `DiagnosticsTelemetryStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`.
- **Tests:** Integration and unit tests verify persistence and legacy read.
- **Examples:** None directly demonstrating pagination.

### DeliveryOperations
- **Primitive query interface:** `src/DeliveryOperations/Contract/DeliveryOperationsQueryInterface.php`
- **Method signature:** `public function find(DeliveryOperationsQueryDTO $query): array;`
- **Query DTO:** `DeliveryOperationsQueryDTO`
- **View DTO:** `DeliveryOperationsViewDTO`
- **MySQL repository:** `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsQueryMysqlRepository.php`
- **Table name:** `maa_event_logging_delivery_operations`
- **Existing filters:** `actorType`, `actorId`, `targetType`, `targetId`, `channel`, `operationType`, `status`, `requestId`, `correlationId`, `after`, `before`.
- **Cursor fields:** `cursorOccurredAt`, `cursorId`.
- **Limit behavior:** Default 50.
- **Existing ordering:** `ORDER BY occurred_at DESC, id DESC`
- **Exception behavior:** Throws `DeliveryOperationsStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`.
- **Tests:** Validation in unit/integration suites.
- **Examples:** None directly demonstrating pagination.

**Note:** All existing primitive contracts must remain unchanged.

## 3. Paginated-Wrapper Inventory
The following domains contain the old wrapper experiment:
- **AuthoritativeAudit:** `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`, `DTO/AuthoritativeAuditQueryCursorDTO.php`, `DTO/AuthoritativeAuditQueryPageDTO.php`, `Service/AuthoritativeAuditPaginatedQueryService.php`. Tests: `Unit/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryServiceTest.php`. Example: none.
- **AuditTrail:** `src/AuditTrail/Contract/AuditTrailPaginatedQueryInterface.php`, `DTO/AuditTrailQueryCursorDTO.php`, `DTO/AuditTrailQueryPageDTO.php`, `Service/AuditTrailPaginatedQueryService.php`. Tests: `Unit/AuditTrail/Service/AuditTrailPaginatedQueryServiceTest.php`. Example: `examples/11-audit-trail-paginated.php`.
- **SecuritySignals:** `src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php`, `DTO/SecuritySignalsQueryCursorDTO.php`, `DTO/SecuritySignalsQueryPageDTO.php`, `Service/SecuritySignalsPaginatedQueryService.php`. Tests: `Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php`. Example: none.
- **BehaviorTrace:** `src/BehaviorTrace/Contract/BehaviorTracePaginatedQueryInterface.php`, `DTO/BehaviorTraceQueryCursorDTO.php`, `DTO/BehaviorTraceQueryPageDTO.php`, `Service/BehaviorTracePaginatedQueryService.php`. Tests: `Unit/BehaviorTrace/Service/BehaviorTracePaginatedQueryServiceTest.php`. Example: none.

**DiagnosticsTelemetry and DeliveryOperations** explicitly do not implement these paginated wrapper artifacts.

## 4. Per-Domain SQL Analysis

### AuthoritativeAudit
- **Distinction:** The system utilizes an authoritative outbox (`maa_event_logging_authoritative_audit_outbox`) and a materialized audit log (`maa_event_logging_authoritative_audit_log`). The Admin listing POC must strictly target the materialized log and must not query or redefine the outbox authority contract unless separately approved.
- **Table:** `maa_event_logging_authoritative_audit_log`
- **Primary key:** `id`
- **Timestamp:** `occurred_at`
- **Relevant indexes:** `idx_auth_audit_log_time (occurred_at, id)`, `idx_auth_audit_log_actor_time (actor_type, actor_id, occurred_at)`, `idx_auth_audit_log_target_time (target_type, target_id, occurred_at)`, `idx_auth_audit_log_action_time (action, occurred_at)`.
- **Prefix support:** `actor_type`, `target_type`, `action` are fully supported as leftmost index prefixes.
- **Candidate sort keys:** `occurred_at`.
- **Candidate default sort:** `occurred_at`.
- **Deterministic tie-breaker:** `id`.
- **Nullable columns:** `actor_id`, `target_id`, `changes`, `ip_address`, `user_agent`, `correlation_id`.
- **JSON columns:** `changes`
- **Enum-like columns:** `actor_type`, `target_type`, `action`, `risk_level`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** Filters like `target_id` or `actor_id` used independently (without their `_type` counterpart) will result in partial or non-indexed full table scans.

### AuditTrail
- **Table:** `maa_event_logging_audit_trail`
- **Primary key:** `id`
- **Timestamp:** `occurred_at`
- **Relevant indexes:** `idx_el_audit_trail_time (occurred_at, id)`, `idx_el_audit_trail_actor_time (actor_type, actor_id, occurred_at)`, `idx_el_audit_trail_event_time (event_key, occurred_at)`.
- **Prefix support:** `actor_type`, `event_key` are full index prefixes. `actor_id` alone without `actor_type` would not hit prefix.
- **Candidate sort keys:** `occurred_at`.
- **Candidate default sort:** `occurred_at`.
- **Deterministic tie-breaker:** `id`.
- **Nullable columns:** `actor_id`, `entity_id`, `subject_type`, `subject_id`, `referrer_route_name`, `referrer_path`, `referrer_host`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `channel`, `operation_type`, `status`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` without `actor_type` will skip prefix.
- **Enum-like columns:** `actor_type`, `event_key`, `severity`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` without `actor_type`, or `requestId` without time prefix.
- **Enum-like columns:** `actor_type`, `action`, `entity_type`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` or `entity_id` without their `_type` will skip the index prefix.
- **Enum-like columns:** `actor_type`, `signal_type`, `severity`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` without `actor_type`, or `severity` alone (which is indexed with time, but might not be fully selective) may require wider scans.
- **Enum-like columns:** `actor_type`, `event_key`, `entity_type`, `subject_type`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id`, `entity_id`, or `subject_id` used without their `_type` counterpart will fail to hit the index prefix.

### SecuritySignals
- **Table:** `maa_event_logging_security_signals`
- **Primary key:** `id`
- **Timestamp:** `occurred_at`
- **Relevant indexes:** `idx_el_security_signals_time (occurred_at, id)`, `idx_el_security_signals_actor_time (actor_type, actor_id, occurred_at)`, `idx_el_security_signals_type_time (signal_type, occurred_at)`.
- **Prefix support:** `actor_type`, `signal_type` are full index prefixes.
- **Candidate sort keys:** `occurred_at`.
- **Candidate default sort:** `occurred_at`.
- **Deterministic tie-breaker:** `id`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `signal_type`, `severity`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` without `actor_type`, or `severity` alone (which is indexed with time, but might not be fully selective) may require wider scans.
- **Enum-like columns:** `actor_type`, `event_key`, `entity_type`, `subject_type`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id`, `entity_id`, or `subject_id` used without their `_type` counterpart will fail to hit the index prefix.

### BehaviorTrace
- **Table:** `maa_event_logging_behavior_trace`
- **Primary key:** `id`
- **Timestamp:** `occurred_at`
- **Relevant indexes:** `idx_el_behavior_trace_time (occurred_at, id)`, `idx_el_behavior_trace_actor_time (actor_type, actor_id, occurred_at)`, `idx_el_behavior_trace_action_time (action, occurred_at)`.
- **Prefix support:** `actor_type`, `action` are full index prefixes.
- **Candidate sort keys:** `occurred_at`.
- **Candidate default sort:** `occurred_at`.
- **Deterministic tie-breaker:** `id`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `action`, `entity_type`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` or `entity_id` without their `_type` will skip the index prefix.
- **Enum-like columns:** `actor_type`, `event_key`, `entity_type`, `subject_type`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id`, `entity_id`, or `subject_id` used without their `_type` counterpart will fail to hit the index prefix.

### DiagnosticsTelemetry
- **Table:** `maa_event_logging_diagnostics_telemetry`
- **Primary key:** `id`
- **Timestamp:** `occurred_at`
- **Relevant indexes:** `idx_diag_telemetry_time (occurred_at, id)`, `idx_diag_telemetry_actor_time (actor_type, actor_id, occurred_at)`, `idx_diag_telemetry_event_time (event_key, occurred_at)`.
- **Prefix support:** `actor_type`, `event_key` are full index prefixes.
- **Candidate sort keys:** `occurred_at`.
- **Candidate default sort:** `occurred_at`.
- **Deterministic tie-breaker:** `id`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `event_key`, `severity`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` without `actor_type`, or `requestId` without time prefix.
- **Enum-like columns:** `actor_type`, `event_key`, `entity_type`, `subject_type`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id`, `entity_id`, or `subject_id` used without their `_type` counterpart will fail to hit the index prefix.

### DeliveryOperations
- **Table:** `maa_event_logging_delivery_operations`
- **Primary key:** `id`
- **Timestamp:** `occurred_at`
- **Relevant indexes:** `idx_delivery_ops_time (occurred_at, id)`, `idx_delivery_ops_actor_time (actor_type, actor_id, occurred_at)`, `idx_delivery_ops_channel_time (channel, occurred_at)`, `idx_delivery_ops_status_time (status, occurred_at)`.
- **Prefix support:** `actor_type`, `channel`, `status` are full index prefixes.
- **Candidate sort keys:** `occurred_at`.
- **Candidate default sort:** `occurred_at`.
- **Deterministic tie-breaker:** `id`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `channel`, `operation_type`, `status`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id` without `actor_type` will skip prefix.
- **Enum-like columns:** `actor_type`, `event_key`, `entity_type`, `subject_type`.
- **Total-count query scope:** Unfiltered count of all records.
- **Filtered-count query scope:** Count restricted by active filters.
- **Data-query scope:** Filtered rows with pagination limits.
- **Risk of divergence:** None, provided the same semantic where-clause logic is utilized across all three scopes.
- **Non-indexed behavior:** `actor_id`, `entity_id`, or `subject_id` used without their `_type` counterpart will fail to hit the index prefix.

*(Note for all tables: `(occurred_at, id)` is declared as a composite index. Backward index scanning is natively supported for descending sort when ordered by `occurred_at DESC, id DESC`.)*

## 5. Stable Persistence API (maatify/persistence v1.1.0)

**Namespace:** `Maatify\Persistence\Pdo\Pagination`

- **`PageRequest`:** `__construct(public readonly int|string|null $page = null, public readonly int|string|null $perPage = null, public readonly ?string $sortBy = null, public readonly ?string $sortDirection = null)`
- **`PageResult`:** `__construct(public readonly array $data, public readonly int $page, public readonly int $perPage, public readonly int $total, public readonly int $filtered, public readonly int $totalPages, public readonly bool $hasNext, public readonly bool $hasPrevious, public readonly string $sortBy, public readonly SortDirectionEnum $sortDirection)`
- **`PaginationConfig`:** `__construct(public readonly int $defaultPerPage, public readonly int $minPerPage, public readonly int $maxPerPage, public readonly SortWhitelist $sortWhitelist, public readonly string $defaultSortBy, public readonly SortDirectionEnum $defaultSortDirection, public readonly string $tieBreakerSortBy, public readonly SortDirectionEnum $tieBreakerDirection)`
- **`SortWhitelist`:** `__construct(array $sorts)`. Throws `InvalidPaginationConfigurationException` on bad keys/paths.
- **`SortDirectionEnum`:** `enum SortDirectionEnum: string { case ASC; case DESC; }`
- **`PdoPaginationQueryDescriptor`:** `__construct(public readonly string $totalSql, public readonly array $totalParams, public readonly string $filteredCountSql, public readonly array $filteredCountParams, public readonly string $dataSql, public readonly array $dataParams)`
- **`PdoPaginator`:** `public function paginate(PDO $pdo, PdoPaginationQueryDescriptor $query, PageRequest $request, PaginationConfig $config, callable $mapper): PageResult`

**Explicit Guarantees:**
- `PdoPaginator` normalizes invalid/missing page values to 1.
- Clamps `perPage` according to `PaginationConfig`.
- Resets page > totalPages to 1.
- Resolves sort keys strictly through `SortWhitelist`.
- Appends deterministic `ORDER BY`, `LIMIT`, and `OFFSET` to the provided `dataSql` (consumer must not append these).
- Requires count queries to return exactly one row and one column.
- Requires mapper output to be an array or object.
- Returns `PageResult`.
- Throws persistence exceptions (`PaginationExecutionException`, `InvalidPaginationConfigurationException`) on failure.
- **Reserved pagination parameters:** `__pagination_limit` and `__pagination_offset`.

## 6. Compatibility Decisions (Mandatory Phase 2 Design)

**Hydration reuse:**
The existing mappers (like `mapRowToDTO()` in `AuditTrailQueryMysqlRepository`) are `private`. Thus, they cannot be reused directly by a separate adapter. Phase 2 must explicitly approve one of these alternatives:
1. Extract an internal domain row mapper shared by both paths.
2. Place new pagination execution behind a repository design that can call the existing mapper without changing the current public contract.
3. Introduce a separately approved Admin DTO and mapper.

**Exception translation:**
`PdoPaginator` throws persistence-layer exceptions, whereas existing package contracts strictly expose domain storage exceptions. Phase 2 design must decide:
- Which persistence exceptions are translated and which domain exception is exposed.
- How the original throwable is preserved as `previous`.
- Strict alignment with existing package exception policies (e.g. `SystemMaatifyException` inheritance).

**Result contract:**
Decide only at Phase 2 design whether the public result will be `PageResult<ExistingDomainDTO>`, a domain-owned Admin page DTO wrapping `PageResult`, or another approved domain contract.

**SQL contract:**
Phase 2 design must define approved structures for:
- Total, Filtered count, and Data SQL generation (or shared filter builders ensuring semantic alignment).
- Sort whitelist mappings.
- Security and visibility constraints.

## 7. POC Candidate Matrix & Recommendation

| Domain | Simplicity | Indexes | Filters | Hydration | Tests | Wrapper Status | Risk | Selection |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **AuditTrail** | High | Strong | Mod | Mod | Strong | Present | Low | **RECOMMENDED** |
| **AuthoritativeAudit** | Mod | Strong | Mod | Mod | Strong | Present | High (Fail-closed) | Rejected |
| **SecuritySignals** | High | Mod | Low | Mod | Mod | Present | Low | Rejected |
| **BehaviorTrace** | High | Strong | Mod | Mod | Mod | Present | Low | Rejected |
| **DiagnosticsTelemetry** | High | Strong | Mod | Mod | Mod | None | Low | Rejected |
| **DeliveryOperations** | Mod | Strong | High | Mod | Mod | None | Low | Rejected |

**Rejection Reasons:**
- `AuthoritativeAudit`: High operational criticality and strict fail-closed requirements make it an unsafe choice for an experimental integration.
- `SecuritySignals`: Though safe (fail-open), it has fewer complex filtering requirements than `AuditTrail`, making it less representative for fully proving the `PdoPaginator`.
- `BehaviorTrace`: Similar to AuditTrail, but AuditTrail is canonically the primary "Views/Reads/Export" track.
- `DiagnosticsTelemetry`, `DeliveryOperations`: Both lack existing wrapper artifacts, making them poor candidates for early migration validation.

**Final Recommendation:** **AuditTrail**.

## 8. Blockers & Constraints

**Blockers to entering Phase 2 design:** None.
**Mandatory decisions that Phase 2 design must resolve:** Result contract, hydration reuse strategy, SQL semantic alignment, exception translation policy.
**Blockers to beginning Phase 2 implementation:** A fully approved Phase 2 design (see Entry Conditions below).

## 9. Exact Phase 2 Entry Conditions
1. Phase 1 audit accepted and merged.
2. Owner approves the selected POC domain.
3. Owner approves a Phase 2 implementation blueprint.
4. Public result contract approved.
5. Filter and sort contract approved.
6. Mapper reuse strategy approved.
7. Exception translation strategy approved.
8. Count/data semantic-alignment strategy approved.
9. Test matrix approved.
10. Only then may a separate implementation PR add `maatify/persistence ^1.1.0`.

## 10. Final Verdict
READY FOR PHASE 2 DESIGN APPROVAL