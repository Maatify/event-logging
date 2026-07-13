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
- **Query DTO:** `AuthoritativeAuditQueryDTO(public ?\DateTimeImmutable $after = null, public ?\DateTimeImmutable $before = null, public ?string $actorType = null, public ?int $actorId = null, public ?string $targetType = null, public ?int $targetId = null, public ?string $action = null, public ?string $correlationId = null, public ?\DateTimeImmutable $cursorOccurredAt = null, public ?int $cursorId = null, public int $limit = 50)`
- **View DTO:** `AuthoritativeAuditViewDTO`
- **MySQL repository:** `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditQueryMysqlRepository.php`
- **Exception behavior:** Throws `AuthoritativeAuditStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. JSON `changes` safely decoded or mapped to null if invalid.
- **Tests:** `tests/Integration/AuthoritativeAudit/AuthoritativeAuditRepositoryTest.php` covers write, query, and cursor pagination roundtrips.
- **Examples:** None.

**SQL Analysis:**
- **Table:** `maa_event_logging_authoritative_audit_log` (Materialized audit log). The authoritative outbox (`maa_event_logging_authoritative_audit_outbox`) MUST NOT be queried for admin listings.
- **Columns:** `id`, `event_id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `changes`, `ip_address`, `user_agent`, `correlation_id`, `occurred_at`.
- **Nullable columns:** `actor_id`, `target_id`, `changes`, `ip_address`, `user_agent`, `correlation_id`.
- **JSON columns:** `changes`
- **Enum-like columns:** `actor_type`, `target_type`, `action`.
- **Indexes:**
  - `idx_auth_audit_log_time (occurred_at, id)`
  - `idx_auth_audit_log_actor_time (actor_type, actor_id, occurred_at)`
  - `idx_auth_audit_log_target_time (target_type, target_id, occurred_at)`
  - `idx_auth_audit_log_action_time (action, occurred_at)`
- **Leftmost-prefix analysis:** `actor_type`, `target_type`, and `action` support leftmost prefix querying.
- **Unsupported/Standalone filter risk:** `actor_id` or `target_id` queried without their respective `_type` will skip the index prefix and result in wider scans.
- **Sort keys:** `occurred_at`.
- **Default ordering:** `occurred_at DESC, id DESC`.
- **Tie-breaker:** `id`.
- **Count/Data Readiness & Semantic Alignment:**
  - No Admin total-count, filtered-count, or offset data query currently exists.
  - The current table and filter set are compatible with building those three queries.
  - Actual semantic alignment is not yet proven.
  - Phase 2 must use one shared filter-construction source, or an equivalent single source of truth, for filtered-count and data SQL.
  - A mismatch between count filters, data filters, nullable-value handling, or parameter normalization is a Phase 2 design and test risk. Specific risks include maintaining the materialized-log boundary (never querying the outbox), handling type/id filter pairs (`actor_id`, `target_id`), handling nullable `actor_id` or `target_id`, unsearchable JSON `changes`, date-range inclusivity, and `correlationId` filters.

### AuditTrail
- **Primitive query interface:** `src/AuditTrail/Contract/AuditTrailQueryInterface.php`
- **Method signature:** `public function find(AuditTrailQueryDTO $query): array;`
- **Query DTO:** `AuditTrailQueryDTO(public ?string $actorType = null, public ?int $actorId = null, public ?string $eventKey = null, public ?string $entityType = null, public ?int $entityId = null, public ?string $subjectType = null, public ?int $subjectId = null, public ?string $requestId = null, public ?string $correlationId = null, public ?\DateTimeImmutable $after = null, public ?\DateTimeImmutable $before = null, public ?\DateTimeImmutable $cursorOccurredAt = null, public ?int $cursorId = null, public int $limit = 50)`
- **View DTO:** `AuditTrailViewDTO`
- **MySQL repository:** `src/AuditTrail/Infrastructure/Mysql/AuditTrailQueryMysqlRepository.php`
- **Exception behavior:** Throws `AuditTrailStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. JSON `metadata` safely decoded.
- **Tests:** `tests/Integration/AuditTrail/AuditTrailRepositoryTest.php` covers write, query, and cursor pagination roundtrips.
- **Examples:** None directly for primitive read.

**SQL Analysis:**
- **Table:** `maa_event_logging_audit_trail`
- **Columns:** `id`, `event_id`, `actor_type`, `actor_id`, `event_key`, `entity_type`, `entity_id`, `subject_type`, `subject_id`, `referrer_route_name`, `referrer_path`, `referrer_host`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`, `metadata`, `occurred_at`.
- **Nullable columns:** `actor_id`, `entity_id`, `subject_type`, `subject_id`, `referrer_route_name`, `referrer_path`, `referrer_host`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `event_key`, `entity_type`, `subject_type`.
- **Indexes:**
  - `idx_el_audit_trail_time (occurred_at, id)`
  - `idx_el_audit_trail_actor_time (actor_type, actor_id, occurred_at)`
  - `idx_el_audit_trail_event_time (event_key, occurred_at)`
  - `idx_el_audit_trail_entity_time (entity_type, entity_id, occurred_at)`
  - `idx_el_audit_trail_subject_time (subject_type, subject_id, occurred_at)`
  - `idx_el_audit_trail_corr_time (correlation_id, occurred_at)`
  - `idx_el_audit_trail_request_time (request_id, occurred_at)`
- **Leftmost-prefix analysis:** `actor_type`, `event_key`, `entity_type`, and `subject_type` support prefix scanning.
- **Unsupported/Standalone filter risk:** Filtering by ID fields (`actor_id`, `entity_id`, `subject_id`) without their type prefix skips the index.
- **Sort keys:** `occurred_at`.
- **Default ordering:** `occurred_at DESC, id DESC`.
- **Tie-breaker:** `id`.
- **Count/Data Readiness & Semantic Alignment:**
  - No Admin total-count, filtered-count, or offset data query currently exists.
  - The current table and filter set are compatible with building those three queries.
  - Actual semantic alignment is not yet proven.
  - Phase 2 must use one shared filter-construction source, or an equivalent single source of truth, for filtered-count and data SQL.
  - A mismatch between count filters, data filters, nullable-value handling, or parameter normalization is a Phase 2 design and test risk. Specific risks include handling type/id filter pairs (`actor_id`, `entity_id`, `subject_id`), handling nullable ID fields, unsearchable JSON `metadata`, date-range inclusivity, and `correlationId` / `requestId` filters.

### SecuritySignals
- **Primitive query interface:** `src/SecuritySignals/Contract/SecuritySignalsQueryInterface.php`
- **Method signature:** `public function find(SecuritySignalsQueryDTO $query): array;`
- **Query DTO:** `SecuritySignalsQueryDTO(public ?\DateTimeImmutable $after = null, public ?\DateTimeImmutable $before = null, public ?string $actorType = null, public ?int $actorId = null, public ?string $signalType = null, public ?string $severity = null, public ?string $correlationId = null, public ?string $requestId = null, public ?\DateTimeImmutable $cursorOccurredAt = null, public ?int $cursorId = null, public int $limit = 50)`
- **View DTO:** `SecuritySignalsViewDTO`
- **MySQL repository:** `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsQueryMysqlRepository.php`
- **Exception behavior:** Throws `SecuritySignalsStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. JSON metadata decoded.
- **Tests:** `tests/Integration/SecuritySignals/SecuritySignalsRepositoryTest.php` covers write and find query execution, and cursor pagination.
- **Examples:** None.

**SQL Analysis:**
- **Table:** `maa_event_logging_security_signals`
- **Columns:** `id`, `event_id`, `actor_type`, `actor_id`, `signal_type`, `severity`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`, `metadata`, `occurred_at`.
- **Nullable columns:** `actor_id`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `signal_type`, `severity`.
- **Indexes:**
  - `idx_el_security_signals_time (occurred_at, id)`
  - `idx_el_security_signals_actor_time (actor_type, actor_id, occurred_at)`
  - `idx_el_security_signals_type_time (signal_type, occurred_at)`
  - `idx_el_security_signals_severity_time (severity, occurred_at)`
  - `idx_el_security_signals_corr_time (correlation_id, occurred_at)`
  - `idx_el_security_signals_request_time (request_id, occurred_at)`
- **Leftmost-prefix analysis:** `actor_type`, `signal_type`, and `severity` support prefix scanning.
- **Unsupported/Standalone filter risk:** `actor_id` without `actor_type` skips the index.
- **Sort keys:** `occurred_at`.
- **Default ordering:** `occurred_at DESC, id DESC`.
- **Tie-breaker:** `id`.
- **Count/Data Readiness & Semantic Alignment:**
  - No Admin total-count, filtered-count, or offset data query currently exists.
  - The current table and filter set are compatible with building those three queries.
  - Actual semantic alignment is not yet proven.
  - Phase 2 must use one shared filter-construction source, or an equivalent single source of truth, for filtered-count and data SQL.
  - A mismatch between count filters, data filters, nullable-value handling, or parameter normalization is a Phase 2 design and test risk. Specific risks include handling type/id filter pairs (`actor_id`), handling nullable `actor_id`, unsearchable JSON `metadata`, date-range inclusivity, and `correlationId` / `requestId` filters.

### BehaviorTrace
- **Primitive query interface:** `src/BehaviorTrace/Contract/BehaviorTraceQueryInterface.php`
- **Method signatures:**
  - `public function find(BehaviorTraceQueryDTO $query): array;`
  - `public function read(?BehaviorTraceCursorDTO $cursor, int $limit = 100): iterable;`
- **Query DTO:** `BehaviorTraceQueryDTO(public ?\DateTimeImmutable $after = null, public ?\DateTimeImmutable $before = null, public ?string $actorType = null, public ?int $actorId = null, public ?string $action = null, public ?string $entityType = null, public ?int $entityId = null, public ?string $correlationId = null, public ?string $requestId = null, public ?\DateTimeImmutable $cursorOccurredAt = null, public ?int $cursorId = null, public int $limit = 50)`
- **View DTO:** `BehaviorTraceEventDTO`
- **MySQL repository:** `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceQueryMysqlRepository.php`
- **Exception behavior:** Throws `BehaviorTraceStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. JSON metadata decoded.
- **Tests:** `tests/Integration/BehaviorTrace/BehaviorTraceRepositoryTest.php` covers legacy read and find.
- **Examples:** None.

**SQL Analysis:**
- **Table:** `maa_event_logging_behavior_trace`
- **Columns:** `id`, `event_id`, `actor_type`, `actor_id`, `action`, `entity_type`, `entity_id`, `metadata`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`, `occurred_at`.
- **Nullable columns:** `actor_id`, `entity_type`, `entity_id`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `action`, `entity_type`.
- **Indexes:**
  - `idx_el_behavior_trace_time (occurred_at, id)`
  - `idx_el_behavior_trace_actor_time (actor_type, actor_id, occurred_at)`
  - `idx_el_behavior_trace_action_time (action, occurred_at)`
  - `idx_el_behavior_trace_entity_time (entity_type, entity_id, occurred_at)`
  - `idx_el_behavior_trace_corr_time (correlation_id, occurred_at)`
  - `idx_el_behavior_trace_request_time (request_id, occurred_at)`
- **Leftmost-prefix analysis:** `actor_type`, `action`, `entity_type` support prefix scanning.
- **Unsupported/Standalone filter risk:** `actor_id` or `entity_id` without their `_type` skip the index.
- **Sort keys:** `occurred_at`.
- **Default ordering:** `occurred_at DESC, id DESC`.
- **Tie-breaker:** `id`.
- **Count/Data Readiness & Semantic Alignment:**
  - No Admin total-count, filtered-count, or offset data query currently exists.
  - The current table and filter set are compatible with building those three queries.
  - Actual semantic alignment is not yet proven.
  - Phase 2 must use one shared filter-construction source, or an equivalent single source of truth, for filtered-count and data SQL.
  - A mismatch between count filters, data filters, nullable-value handling, or parameter normalization is a Phase 2 design and test risk. Specific risks include handling type/id filter pairs (`actor_id`, `entity_id`), handling nullable ID fields, unsearchable JSON `metadata`, date-range inclusivity, and `correlationId` / `requestId` filters.

### DiagnosticsTelemetry
- **Primitive query interface:** `src/DiagnosticsTelemetry/Contract/DiagnosticsTelemetryQueryInterface.php`
- **Method signatures:**
  - `public function find(DiagnosticsTelemetryQueryDTO $query): array;`
  - `public function read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100): iterable;`
- **Query DTO:** `DiagnosticsTelemetryQueryDTO(public ?\DateTimeImmutable $after = null, public ?\DateTimeImmutable $before = null, public ?string $actorType = null, public ?int $actorId = null, public ?string $eventKey = null, public ?string $severity = null, public ?string $correlationId = null, public ?string $requestId = null, public ?\DateTimeImmutable $cursorOccurredAt = null, public ?int $cursorId = null, public int $limit = 50)`
- **View DTO:** `DiagnosticsTelemetryEventDTO`
- **MySQL repository:** `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryQueryMysqlRepository.php`
- **Exception behavior:** Throws `DiagnosticsTelemetryStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. JSON metadata decoded.
- **Tests:** `tests/Integration/DiagnosticsTelemetry/DiagnosticsTelemetryRepositoryTest.php` covers legacy read and find.
- **Examples:** None.

**SQL Analysis:**
- **Table:** `maa_event_logging_diagnostics_telemetry`
- **Columns:** `id`, `event_id`, `event_key`, `severity`, `actor_type`, `actor_id`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`, `duration_ms`, `metadata`, `occurred_at`.
- **Nullable columns:** `actor_id`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`, `duration_ms`, `metadata`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `actor_type`, `event_key`, `severity`.
- **Indexes:**
  - `idx_diag_telemetry_time (occurred_at, id)`
  - `idx_diag_telemetry_actor_time (actor_type, actor_id, occurred_at)`
  - `idx_diag_telemetry_event_time (event_key, occurred_at)`
  - `idx_diag_telemetry_severity_time (severity, occurred_at)`
  - `idx_diag_telemetry_correlation_time (correlation_id, occurred_at)`
  - `idx_diag_telemetry_request_time (request_id, occurred_at)`
  - `idx_diag_telemetry_route_time (route_name, occurred_at)`
- **Leftmost-prefix analysis:** `actor_type`, `event_key`, `severity` support prefix scanning.
- **Unsupported/Standalone filter risk:** `actor_id` without `actor_type` skips the index.
- **Sort keys:** `occurred_at`.
- **Default ordering:** `occurred_at DESC, id DESC`.
- **Tie-breaker:** `id`.
- **Count/Data Readiness & Semantic Alignment:**
  - No Admin total-count, filtered-count, or offset data query currently exists.
  - The current table and filter set are compatible with building those three queries.
  - Actual semantic alignment is not yet proven.
  - Phase 2 must use one shared filter-construction source, or an equivalent single source of truth, for filtered-count and data SQL.
  - A mismatch between count filters, data filters, nullable-value handling, or parameter normalization is a Phase 2 design and test risk. Specific risks include handling type/id filter pairs (`actor_id`), handling nullable `actor_id`, unsearchable JSON `metadata`, date-range inclusivity, and `correlationId` / `requestId` filters.

### DeliveryOperations
- **Primitive query interface:** `src/DeliveryOperations/Contract/DeliveryOperationsQueryInterface.php`
- **Method signature:** `public function find(DeliveryOperationsQueryDTO $query): array;`
- **Query DTO:** `DeliveryOperationsQueryDTO(public ?\DateTimeImmutable $after = null, public ?\DateTimeImmutable $before = null, public ?string $actorType = null, public ?int $actorId = null, public ?string $targetType = null, public ?int $targetId = null, public ?string $channel = null, public ?string $operationType = null, public ?string $status = null, public ?string $correlationId = null, public ?string $requestId = null, public ?\DateTimeImmutable $cursorOccurredAt = null, public ?int $cursorId = null, public int $limit = 50)`
- **View DTO:** `DeliveryOperationsViewDTO`
- **MySQL repository:** `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsQueryMysqlRepository.php`
- **Exception behavior:** Throws `DeliveryOperationsStorageException`.
- **Hydration:** Private method `mapRowToDTO(array $row)`. JSON metadata decoded.
- **Tests:** `tests/Integration/DeliveryOperations/DeliveryOperationsRepositoryTest.php` covers write, find queries, cursor logic and timezone tests.
- **Examples:** None.

**SQL Analysis:**
- **Table:** `maa_event_logging_delivery_operations`
- **Columns:** `id`, `event_id`, `channel`, `operation_type`, `actor_type`, `actor_id`, `target_type`, `target_id`, `status`, `attempt_no`, `scheduled_at`, `completed_at`, `correlation_id`, `request_id`, `provider`, `provider_message_id`, `error_code`, `error_message`, `metadata`, `occurred_at`.
- **Nullable columns:** `actor_type`, `actor_id`, `target_type`, `target_id`, `scheduled_at`, `completed_at`, `correlation_id`, `request_id`, `provider`, `provider_message_id`, `error_code`, `error_message`.
- **JSON columns:** `metadata`
- **Enum-like columns:** `channel`, `operation_type`, `status`, `actor_type`, `target_type`.
- **Indexes:**
  - `idx_delivery_ops_time (occurred_at, id)`
  - `idx_delivery_ops_actor_time (actor_type, actor_id, occurred_at)`
  - `idx_delivery_ops_channel_time (channel, occurred_at)`
  - `idx_delivery_ops_type_time (operation_type, occurred_at)`
  - `idx_delivery_ops_status_time (status, occurred_at)`
  - `idx_delivery_ops_target_time (target_type, target_id, occurred_at)`
  - `idx_delivery_ops_correlation_time (correlation_id, occurred_at)`
  - `idx_delivery_ops_request_time (request_id, occurred_at)`
- **Leftmost-prefix analysis:** `actor_type`, `channel`, `operation_type`, `status`, `target_type` support prefix scanning.
- **Unsupported/Standalone filter risk:** `actor_id` or `target_id` without their `_type` skip the index.
- **Sort keys:** `occurred_at`.
- **Default ordering:** `occurred_at DESC, id DESC`.
- **Tie-breaker:** `id`.
- **Count/Data Readiness & Semantic Alignment:**
  - No Admin total-count, filtered-count, or offset data query currently exists.
  - The current table and filter set are compatible with building those three queries.
  - Actual semantic alignment is not yet proven.
  - Phase 2 must use one shared filter-construction source, or an equivalent single source of truth, for filtered-count and data SQL.
  - A mismatch between count filters, data filters, nullable-value handling, or parameter normalization is a Phase 2 design and test risk. Specific risks include handling type/id filter pairs (`actor_id`, `target_id`), handling nullable ID fields, unsearchable JSON `metadata`, date-range inclusivity, and `correlationId` / `requestId` filters.

*(Note: The primitive query contracts remain unchanged. All tables declare an index on `(occurred_at, id)` natively enabling deterministic backward index scans for ordering.)*

## 3. Paginated-Wrapper Inventory
Four domains implement the old paginated wrapper experiment:

- **AuthoritativeAudit:**
  - `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`
  - `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTO.php`
  - `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTO.php`
  - `src/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryService.php`
  - Tests: `tests/Unit/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryServiceTest.php` covers empty limit, cursor generation, and exception pass-through.
  - Examples: None found.

- **AuditTrail:**
  - `src/AuditTrail/Contract/AuditTrailPaginatedQueryInterface.php`
  - `src/AuditTrail/DTO/AuditTrailQueryCursorDTO.php`
  - `src/AuditTrail/DTO/AuditTrailQueryPageDTO.php`
  - `src/AuditTrail/Service/AuditTrailPaginatedQueryService.php`
  - Tests: `tests/Unit/AuditTrail/Service/AuditTrailPaginatedQueryServiceTest.php` covers empty limit, cursor generation, and exception pass-through.
  - Examples: None found. (Note: previous assumed example file does not exist).

- **SecuritySignals:**
  - `src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php`
  - `src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php`
  - `src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php`
  - `src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php`
  - Tests: `tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php` covers empty limit, cursor generation, and exception pass-through.
  - Examples: None found.

- **BehaviorTrace:**
  - `src/BehaviorTrace/Contract/BehaviorTracePaginatedQueryInterface.php`
  - `src/BehaviorTrace/DTO/BehaviorTraceQueryCursorDTO.php`
  - `src/BehaviorTrace/DTO/BehaviorTraceQueryPageDTO.php`
  - `src/BehaviorTrace/Service/BehaviorTracePaginatedQueryService.php`
  - Tests: `tests/Unit/BehaviorTrace/Service/BehaviorTracePaginatedQueryServiceTest.php` covers empty limit, cursor generation, and exception pass-through.
  - Examples: None found.

**DiagnosticsTelemetry and DeliveryOperations** explicitly DO NOT implement these paginated wrapper artifacts.

## 4. Stable Persistence API (maatify/persistence v1.1.0)

**Namespace:** `Maatify\Persistence\Pdo\Pagination`

- **`PageRequest`:**
  ```php
  public function __construct(
      public int|string|null $page = null,
      public int|string|null $perPage = null,
      public ?string $sortBy = null,
      public ?string $sortDirection = null
  )
  ```
- **`PageResult`:**
  ```php
  public function __construct(
      public array $data,
      public int $page,
      public int $perPage,
      public int $total,
      public int $filtered,
      public int $totalPages,
      public bool $hasNext,
      public bool $hasPrevious,
      public string $sortBy,
      public SortDirectionEnum $sortDirection
  )
  ```
- **`PaginationConfig`:**
  ```php
  public function __construct(
      public SortWhitelist $sortWhitelist,
      public string $defaultSortBy,
      public SortDirectionEnum $defaultSortDirection,
      public string $tieBreakerSortBy,
      public SortDirectionEnum $tieBreakerDirection,
      public int $defaultPerPage = 20,
      public int $minPerPage = 1,
      public int $maxPerPage = 200,
  )
  ```
- **`SortWhitelist`:**
  ```php
  public function __construct(array $sorts)
  ```
- **`SortDirectionEnum`:**
  ```php
  enum SortDirectionEnum: string
  {
      case ASC = 'ASC';
      case DESC = 'DESC';
  }
  ```
- **`PdoPaginationQueryDescriptor`:**
  ```php
  public function __construct(
      public string $totalSql,
      public array $totalParams,
      public string $filteredCountSql,
      public array $filteredCountParams,
      public string $dataSql,
      public array $dataParams,
  )
  ```
- **`PdoPaginator`:**
  ```php
  public function paginate(
      PDO $pdo,
      PdoPaginationQueryDescriptor $query,
      PageRequest $request,
      PaginationConfig $config,
      callable $mapper
  ): PageResult
  ```

**Exceptions:**
- `Maatify\Persistence\Exception\InvalidPaginationConfigurationException`
- `Maatify\Persistence\Exception\InvalidPaginationQueryException`
- `Maatify\Persistence\Exception\PaginationExecutionException`

**Explicit Guarantees & Restrictions:**
- `PdoPaginator` normalizes invalid/missing page values to 1.
- Resets a page greater than the last page to page 1.
- Clamps `perPage` according to `PaginationConfig`.
- Resolves sort keys strictly through `SortWhitelist`.
- Appends deterministic `ORDER BY`, `LIMIT`, and `OFFSET` to the provided `dataSql`.
- Consumer `dataSql` must not contain the paginator-owned ordering and pagination suffix.
- Count queries must return exactly one row and one column.
- Mapper output must be an array or object.
- Returns `PageResult`.
- Throws persistence pagination exceptions on invalid configuration, query, or execution state.
- **Descriptor Restrictions:** SQL must not be empty, must not contain `;`, must not reference reserved `__pagination_*` parameters. Parameter keys must not start with `:`, must match the allowed identifier pattern, and must not use the reserved prefix. Values are strictly `string|int|bool|null`.

## 5. Compatibility Decisions (Mandatory Phase 2 Design)

**Hydration reuse:**
The existing mappers (`mapRowToDTO()`) in the repositories are `private` and cannot be reused directly by a separate adapter. Phase 2 must select a strategy:
- Extract an internal domain row mapper shared by both paths.
- Place new pagination execution behind a repository design that can call the existing mapper without changing the current public contract.
- Introduce a separately approved Admin DTO and mapper.

**Exception translation:**
`PdoPaginator` throws persistence exceptions (e.g., `PaginationExecutionException`), but event-logging query contracts expose domain storage exceptions. Phase 2 design must decide:
- Which persistence exceptions are translated and which domain exception is exposed.
- How the original throwable is preserved as `previous`.
- How compatibility with existing package exception policy is maintained.

**Result contract:**
Decide at Phase 2 design whether the public result will be `PageResult<ExistingDomainDTO>`, a domain-owned Admin page DTO wrapping `PageResult`, or another approved domain contract.

**SQL contract:**
Phase 2 design must specify the approved contract for:
- Total SQL, Filtered count SQL, and Data SQL generation (or shared filter builder mechanism for semantic alignment).
- Sort whitelist, default sorting, tie-breaker, parameter normalization, and security/visibility constraints.

## 6. POC Candidate Matrix & Recommendation

| Domain | Schema Simplicity | Hydration Complexity | Filter Breadth | Operational Risk | Selection |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **AuditTrail** | Straightforward entity/subject model | Decoding JSON metadata | Broad (actor, eventKey, entity, subject) | Moderate | **RECOMMENDED** |
| **AuthoritativeAudit** | Outbox vs. materialized log duality | Decoding JSON changes | Broad | High (fail-closed) | Rejected |
| **SecuritySignals** | Straightforward | Decoding JSON metadata | Narrow (actor, signalType, severity) | Low | Rejected |
| **BehaviorTrace** | Straightforward | Decoding JSON metadata | Moderate | Low | Rejected |
| **DiagnosticsTelemetry** | Includes duration and routing fields | Decoding JSON metadata | Narrow | Low | Rejected |
| **DeliveryOperations** | Complex enum states and provider tracking | Decoding JSON metadata | Very Broad | Moderate | Rejected |

**Rejection Reasons:**
- **AuthoritativeAudit:** Rejected because its strict fail-closed requirements and outbox-vs-log duality make it an unsafe choice for an initial experimental POC.
- **SecuritySignals:** Rejected because it has fewer filtering dimensions and a narrower use-case, making it less representative for validating complex count/data semantic alignment.
- **BehaviorTrace:** Rejected because it focuses on tracking operational activity for mutations rather than the canonical read/listing purpose that AuditTrail serves.
- **DiagnosticsTelemetry:** Rejected because its schema focuses purely on internal system/routing diagnostics, making it less representative of typical user-facing entity listings.
- **DeliveryOperations:** Rejected because its schema complexity (multiple state enums, delivery attempt tracking) would overcomplicate the validation of base pagination and extraction mechanics.

**Final Recommendation:** **AuditTrail**.
Selected because it has broad, representative filters, a clear domain read/listing purpose, suitable indexes, existing query and integration-test coverage, and moderate operational risk. It provides the ideal foundation to validate count/data semantic alignment, mapper extraction, sorting, and exception translation.

## 7. Blockers & Constraints

- **Blockers to entering Phase 2 design:** None.
- **Mandatory decisions that Phase 2 design must resolve:** Mapper reuse strategy, exception translation strategy, result contract, SQL semantic-alignment, filtering, and sort contracts.
- **Blockers to beginning Phase 2 implementation:** The 10 explicit entry conditions listed below.
*(The Phase 1 verdict strictly does not authorize Composer changes, runtime implementation, new DTOs, new repositories, or new tests.)*

## 8. Exact Phase 2 Entry Conditions
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

## 9. Final Verdict
READY FOR PHASE 2 DESIGN APPROVAL