# Admin Query Capability Inventory

## AuthoritativeAudit

### 1. Query Contract
- **Interface Name:** `AuthoritativeAuditQueryInterface`
- **Path:** `src/AuthoritativeAudit/Contract/AuthoritativeAuditQueryInterface.php`
- **Methods:**
  - `find(AuthoritativeAuditQueryDTO $query): array`
- **Exceptions:** Documented in phpdoc or thrown (check `Exception` imports).

### 2. Query DTO
- **DTO Name:** `AuthoritativeAuditQueryDTO`
- **Path:** `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryDTO.php`
- **Filters/Fields:** `after`, `before`, `actorType`, `actorId`, `targetType`, `targetId`, `action`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`
- **Limit Support:** Yes
- **Cursor Fields:** Yes (`cursorOccurredAt`, `cursorId`)
- **JsonSerializable:** Yes

### 3. View DTO
- **DTO Name:** `AuthoritativeAuditViewDTO`
- **Path:** `src/AuthoritativeAudit/DTO/AuthoritativeAuditViewDTO.php`
- **Returned Fields:** `id` (int), `eventId` (string), `actorType` (string), `actorId` (int), `action` (string), `targetType` (string), `targetId` (int), `ipAddress` (string), `userAgent` (string), `correlationId` (string), `changes` (array), `occurredAt` (\DateTimeImmutable)
- **JsonSerializable:** Yes

### 4. Repository Implementation
- **Repository Name:** `AuthoritativeAuditQueryMysqlRepository`
- **Path:** `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditQueryMysqlRepository.php`
- **Table Read From:** `maa_event_logging_authoritative_audit_log`
- **Result Ordering:** Stable DESC (`ORDER BY occurred_at DESC, id DESC`) is expected.
- **Hydration Method:** Manual extraction (e.g., `mapRowToDTO`), handles invalid JSON gracefully by ignoring/returning null.
- **Exception Handling:** Wraps PDOException in domain-specific storage exception (e.g., `AuthoritativeAuditStorageException`).

### 5. Database Support
- **Filter Fields in Schema:** `actor_type`, `actor_id`, `target_type`, `target_id`, `correlation_id`, `occurred_at`, `action`.
- **Indexes Supporting Filters/Sort:** `idx_auth_audit_log_time (occurred_at, id)`, `idx_auth_audit_log_actor_time (actor_type, actor_id, occurred_at)`, `idx_auth_audit_log_target_time (target_type, target_id, occurred_at)`, `idx_auth_audit_log_correlation_time (correlation_id, occurred_at)`, `idx_auth_audit_log_action_time (action, occurred_at)`.
- **Un-indexed/Missing Filters:** None. All query DTO fields are indexed.
- **Un-queryable Fields:** `ip_address`, `user_agent`, `changes`.

### 6. Test Coverage
- **Unit Tests:** `AuthoritativeAuditQueryMysqlRepositoryTest` covers `find()` logic, filter WHERE clause construction, cursor bounds, mapping, corrupt JSON, and PDO failures. `AuthoritativeAuditQueryDTOTest` covers defaults and full hydration.
- **Integration Tests:** None.
- **Cursor Pagination Coverage:** Unit tested.
- **Filter Coverage:** Unit tested.
- **Corrupt JSON Coverage:** Unit tested (returns null for `changes`).
- **Storage Failure Coverage:** Unit tested.

## AuditTrail

### 1. Query Contract
- **Interface Name:** `AuditTrailQueryInterface`
- **Path:** `src/AuditTrail/Contract/AuditTrailQueryInterface.php`
- **Methods:**
  - `find(AuditTrailQueryDTO $query): array`
- **Exceptions:** Documented in phpdoc or thrown (check `Exception` imports).

### 2. Query DTO
- **DTO Name:** `AuditTrailQueryDTO`
- **Path:** `src/AuditTrail/DTO/AuditTrailQueryDTO.php`
- **Filters/Fields:** `actorType`, `actorId`, `eventKey`, `entityType`, `entityId`, `subjectType`, `subjectId`, `requestId`, `correlationId`, `after`, `before`, `cursorOccurredAt`, `cursorId`, `limit`
- **Limit Support:** Yes
- **Cursor Fields:** Yes (`cursorOccurredAt`, `cursorId`)
- **JsonSerializable:** Yes

### 3. View DTO
- **DTO Name:** `AuditTrailViewDTO`
- **Path:** `src/AuditTrail/DTO/AuditTrailViewDTO.php`
- **Returned Fields:** `id` (int), `eventId` (string), `actorType` (string), `actorId` (int), `eventKey` (string), `entityType` (string), `entityId` (int), `subjectType` (string), `subjectId` (int), `referrerRouteName` (string), `referrerPath` (string), `referrerHost` (string), `correlationId` (string), `requestId` (string), `routeName` (string), `ipAddress` (string), `userAgent` (string), `metadata` (array), `occurredAt` (DateTimeImmutable)
- **JsonSerializable:** Yes

### 4. Repository Implementation
- **Repository Name:** `AuditTrailQueryMysqlRepository`
- **Path:** `src/AuditTrail/Infrastructure/Mysql/AuditTrailQueryMysqlRepository.php`
- **Table Read From:** `maa_event_logging_audit_trail`
- **Result Ordering:** Stable DESC (`ORDER BY occurred_at DESC, id DESC`) is expected.
- **Hydration Method:** Manual extraction (e.g., `mapRowToDTO`), handles invalid JSON gracefully by ignoring/returning null.
- **Exception Handling:** Wraps PDOException in domain-specific storage exception (e.g., `AuditTrailStorageException`).

### 5. Database Support
- **Filter Fields in Schema:** `actor_type`, `actor_id`, `event_key`, `entity_type`, `entity_id`, `subject_type`, `subject_id`, `request_id`, `correlation_id`, `occurred_at`.
- **Indexes Supporting Filters/Sort:** `idx_el_audit_trail_time`, `idx_el_audit_trail_actor_time`, `idx_el_audit_trail_event_time`, `idx_el_audit_trail_entity_time`, `idx_el_audit_trail_subject_time`, `idx_el_audit_trail_req_time`, `idx_el_audit_trail_corr_time`.
- **Un-indexed/Missing Filters:** None. Highly indexed schema.
- **Un-queryable Fields:** `referrer_*`, `ip_address`, `user_agent`, `metadata`.

### 6. Test Coverage
- **Unit Tests:** Repository and DTO heavily unit tested for all find scenarios, cursors, mapping, and exceptions.
- **Integration Tests:** None.
- **Cursor Pagination Coverage:** Unit tested.
- **Filter Coverage:** Unit tested.
- **Corrupt JSON Coverage:** Unit tested.
- **Storage Failure Coverage:** Unit tested.

## SecuritySignals

### 1. Query Contract
- **Interface Name:** `SecuritySignalsQueryInterface`
- **Path:** `src/SecuritySignals/Contract/SecuritySignalsQueryInterface.php`
- **Methods:**
  - `find(SecuritySignalsQueryDTO $query): array`
- **Exceptions:** Documented in phpdoc or thrown (check `Exception` imports).

### 2. Query DTO
- **DTO Name:** `SecuritySignalsQueryDTO`
- **Path:** `src/SecuritySignals/DTO/SecuritySignalsQueryDTO.php`
- **Filters/Fields:** `after`, `before`, `actorType`, `actorId`, `signalType`, `severity`, `requestId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`
- **Limit Support:** Yes
- **Cursor Fields:** Yes (`cursorOccurredAt`, `cursorId`)
- **JsonSerializable:** Yes

### 3. View DTO
- **DTO Name:** `SecuritySignalsViewDTO`
- **Path:** `src/SecuritySignals/DTO/SecuritySignalsViewDTO.php`
- **Returned Fields:** `id` (int), `eventId` (string), `actorType` (string), `actorId` (int), `signalType` (string), `severity` (string), `correlationId` (string), `requestId` (string), `routeName` (string), `ipAddress` (string), `userAgent` (string), `metadata` (array), `occurredAt` (\DateTimeImmutable)
- **JsonSerializable:** Yes

### 4. Repository Implementation
- **Repository Name:** `SecuritySignalsQueryMysqlRepository`
- **Path:** `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsQueryMysqlRepository.php`
- **Table Read From:** `maa_event_logging_security_signals`
- **Result Ordering:** Stable DESC (`ORDER BY occurred_at DESC, id DESC`) is expected.
- **Hydration Method:** Manual extraction (e.g., `mapRowToDTO`), handles invalid JSON gracefully by ignoring/returning null.
- **Exception Handling:** Wraps PDOException in domain-specific storage exception (e.g., `SecuritySignalsStorageException`).

### 5. Database Support
- **Filter Fields in Schema:** `actor_type`, `actor_id`, `signal_type`, `severity`, `request_id`, `correlation_id`, `occurred_at`.
- **Indexes Supporting Filters/Sort:** Time, actor_time, signal_time, severity_time, correlation_time, request_time indexes all exist.
- **Un-indexed/Missing Filters:** None.
- **Un-queryable Fields:** `ip_address`, `user_agent`, `metadata`, `route_name`.

### 6. Test Coverage
- **Unit Tests:** Complete coverage for `SecuritySignalsQueryMysqlRepository` and `SecuritySignalsQueryDTO`.
- **Integration Tests:** None.
- **Cursor Pagination Coverage:** Unit tested.
- **Filter Coverage:** Unit tested.
- **Corrupt JSON Coverage:** Unit tested.
- **Storage Failure Coverage:** Unit tested.

## BehaviorTrace

### 1. Query Contract
- **Interface Name:** `BehaviorTraceQueryInterface`
- **Path:** `src/BehaviorTrace/Contract/BehaviorTraceQueryInterface.php`
- **Methods:**
  - `find(BehaviorTraceQueryDTO $query): array`
  - `read(?BehaviorTraceCursorDTO $cursor, int $limit = 100): iterable`
- **Exceptions:** Documented in phpdoc or thrown (check `Exception` imports).

### 2. Query DTO
- **DTO Name:** `BehaviorTraceQueryDTO`
- **Path:** `src/BehaviorTrace/DTO/BehaviorTraceQueryDTO.php`
- **Filters/Fields:** `after`, `before`, `actorType`, `actorId`, `entityType`, `entityId`, `action`, `requestId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`
- **Limit Support:** Yes
- **Cursor Fields:** Yes (`cursorOccurredAt`, `cursorId`)
- **JsonSerializable:** Yes

### 3. View DTO
- **DTO Name:** `BehaviorTraceEventDTO`
- **Path:** `src/BehaviorTrace/DTO/BehaviorTraceEventDTO.php`
- **Returned Fields:** `eventId` (string), `action` (string), `entityType` (string), `entityId` (int), `context` (BehaviorTraceContextDTO), `metadata` (array)
- **JsonSerializable:** Yes

### 4. Repository Implementation
- **Repository Name:** `BehaviorTraceQueryMysqlRepository`
- **Path:** `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceQueryMysqlRepository.php`
- **Table Read From:** `maa_event_logging_behavior_trace`
- **Result Ordering:** Stable DESC (`ORDER BY occurred_at DESC, id DESC`) is expected.
- **Hydration Method:** Manual extraction (e.g., `mapRowToDTO`), handles invalid JSON gracefully by ignoring/returning null.
- **Exception Handling:** Wraps PDOException in domain-specific storage exception (e.g., `BehaviorTraceStorageException`).

### 5. Database Support
- **Filter Fields in Schema:** `actor_type`, `actor_id`, `action`, `entity_type`, `entity_id`, `request_id`, `correlation_id`, `occurred_at`.
- **Indexes Supporting Filters/Sort:** Time, actor_time, action_time, entity_time, correlation_time, request_time.
- **Un-indexed/Missing Filters:** None.
- **Un-queryable Fields:** `ip_address`, `user_agent`, `metadata`, `route_name`.

### 6. Test Coverage
- **Unit Tests:** Complete coverage for `find()` and `read()` legacy method, DTOs, JSON parsing.
- **Integration Tests:** None.
- **Cursor Pagination Coverage:** Unit tested.
- **Filter Coverage:** Unit tested.
- **Corrupt JSON Coverage:** Unit tested.
- **Storage Failure Coverage:** Unit tested.

## DiagnosticsTelemetry

### 1. Query Contract
- **Interface Name:** `DiagnosticsTelemetryQueryInterface`
- **Path:** `src/DiagnosticsTelemetry/Contract/DiagnosticsTelemetryQueryInterface.php`
- **Methods:**
  - `find(DiagnosticsTelemetryQueryDTO $query): array`
  - `read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100): iterable`
- **Exceptions:** Documented in phpdoc or thrown (check `Exception` imports).

### 2. Query DTO
- **DTO Name:** `DiagnosticsTelemetryQueryDTO`
- **Path:** `src/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryQueryDTO.php`
- **Filters/Fields:** `after`, `before`, `actorType`, `actorId`, `eventKey`, `severity`, `requestId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`
- **Limit Support:** Yes
- **Cursor Fields:** Yes (`cursorOccurredAt`, `cursorId`)
- **JsonSerializable:** Yes

### 3. View DTO
- **DTO Name:** `DiagnosticsTelemetryEventDTO`
- **Path:** `src/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryEventDTO.php`
- **Returned Fields:** `eventId` (string), `eventKey` (string), `severity` (DiagnosticsTelemetrySeverityInterface), `context` (DiagnosticsTelemetryContextDTO), `durationMs` (int), `metadata` (array)
- **JsonSerializable:** Yes

### 4. Repository Implementation
- **Repository Name:** `DiagnosticsTelemetryQueryMysqlRepository`
- **Path:** `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryQueryMysqlRepository.php`
- **Table Read From:** `maa_event_logging_diagnostics_telemetry`
- **Result Ordering:** Stable DESC (`ORDER BY occurred_at DESC, id DESC`) is expected.
- **Hydration Method:** Manual extraction (e.g., `mapRowToDTO`), handles invalid JSON gracefully by ignoring/returning null.
- **Exception Handling:** Wraps PDOException in domain-specific storage exception (e.g., `DiagnosticsTelemetryStorageException`).

### 5. Database Support
- **Filter Fields in Schema:** `actor_type`, `actor_id`, `event_key`, `severity`, `request_id`, `correlation_id`, `occurred_at`.
- **Indexes Supporting Filters/Sort:** Time, actor_time, event_time, severity_time, correlation_time, request_time.
- **Un-indexed/Missing Filters:** None.
- **Un-queryable Fields:** `ip_address`, `user_agent`, `metadata`, `route_name`, `duration_ms`.

### 6. Test Coverage
- **Unit Tests:** Complete coverage for `find()`, `read()`, cursor usage, mapping, exceptions.
- **Integration Tests:** None.
- **Cursor Pagination Coverage:** Unit tested.
- **Filter Coverage:** Unit tested.
- **Corrupt JSON Coverage:** Unit tested.
- **Storage Failure Coverage:** Unit tested.

## DeliveryOperations

### 1. Query Contract
- **Interface Name:** `DeliveryOperationsQueryInterface`
- **Path:** `src/DeliveryOperations/Contract/DeliveryOperationsQueryInterface.php`
- **Methods:**
  - `find(DeliveryOperationsQueryDTO $query): array`
- **Exceptions:** Documented in phpdoc or thrown (check `Exception` imports).

### 2. Query DTO
- **DTO Name:** `DeliveryOperationsQueryDTO`
- **Path:** `src/DeliveryOperations/DTO/DeliveryOperationsQueryDTO.php`
- **Filters/Fields:** `after`, `before`, `actorType`, `actorId`, `targetType`, `targetId`, `channel`, `operationType`, `status`, `requestId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`
- **Limit Support:** Yes
- **Cursor Fields:** Yes (`cursorOccurredAt`, `cursorId`)
- **JsonSerializable:** Yes

### 3. View DTO
- **DTO Name:** `DeliveryOperationsViewDTO`
- **Path:** `src/DeliveryOperations/DTO/DeliveryOperationsViewDTO.php`
- **Returned Fields:** `id` (int), `eventId` (string), `channel` (string), `operationType` (string), `actorType` (string), `actorId` (int), `targetType` (string), `targetId` (int), `status` (string), `attemptNo` (int), `scheduledAt` (\DateTimeImmutable), `completedAt` (\DateTimeImmutable), `correlationId` (string), `requestId` (string), `provider` (string), `providerMessageId` (string), `errorCode` (string), `errorMessage` (string), `metadata` (array), `occurredAt` (\DateTimeImmutable)
- **JsonSerializable:** Yes

### 4. Repository Implementation
- **Repository Name:** `DeliveryOperationsQueryMysqlRepository`
- **Path:** `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsQueryMysqlRepository.php`
- **Table Read From:** `maa_event_logging_delivery_operations`
- **Result Ordering:** Stable DESC (`ORDER BY occurred_at DESC, id DESC`) is expected.
- **Hydration Method:** Manual extraction (e.g., `mapRowToDTO`), handles invalid JSON gracefully by ignoring/returning null.
- **Exception Handling:** Wraps PDOException in domain-specific storage exception (e.g., `DeliveryOperationsStorageException`).

### 5. Database Support
- **Filter Fields in Schema:** `actor_type`, `actor_id`, `target_type`, `target_id`, `channel`, `operation_type`, `status`, `request_id`, `correlation_id`, `occurred_at`.
- **Indexes Supporting Filters/Sort:** Time, channel_time, operation_time, status_time, target_time, actor_time, correlation_time, request_time.
- **Un-indexed/Missing Filters:** None.
- **Un-queryable Fields:** `provider`, `provider_message_id`, `error_code`, `error_message`, `metadata`, `attempt_no`, `scheduled_at`, `completed_at`.

### 6. Test Coverage
- **Unit Tests:** Complete coverage for repository SQL assembly, cursor paging, and mapping.
- **Integration Tests:** None.
- **Cursor Pagination Coverage:** Unit tested.
- **Filter Coverage:** Unit tested.
- **Corrupt JSON Coverage:** Unit tested.
- **Storage Failure Coverage:** Unit tested.

## Gap Matrix

| Domain | Contract | Query DTO | View DTO | Repository | Cursor Paging | Date Filters | Actor Filters | Request/Correlation | Domain Filters | Integration Tests | Actual Gaps |
|--------|----------|-----------|----------|------------|---------------|--------------|---------------|---------------------|----------------|-------------------|-------------|
| AuthoritativeAudit | Yes | Yes | Yes | Yes | Yes | Yes (`after`/`before`) | Yes (`Type`/`Id`) | Only Correlation | Target, Action | **No** | Missing Integration Tests. `requestId` intentionally absent. |
| AuditTrail | Yes | Yes | Yes | Yes | Yes | Yes (`after`/`before`) | Yes (`Type`/`Id`) | Both | EventKey, Entity, Subject | **No** | Missing Integration Tests. |
| SecuritySignals | Yes | Yes | Yes | Yes | Yes | Yes (`after`/`before`) | Yes (`Type`/`Id`) | Both | Signal, Severity | **No** | Missing Integration Tests. |
| BehaviorTrace | Yes | Yes | Yes (EventDTO) | Yes | Yes | Yes (`after`/`before`) | Yes (`Type`/`Id`) | Both | Action, Entity | **No** | View DTO named `EventDTO` instead of `ViewDTO`. Missing Integration Tests. |
| DiagnosticsTelemetry | Yes | Yes | Yes (EventDTO) | Yes | Yes | Yes (`after`/`before`) | Yes (`Type`/`Id`) | Both | EventKey, Severity | **No** | View DTO named `EventDTO` instead of `ViewDTO`. Missing Integration Tests. |
| DeliveryOperations | Yes | Yes | Yes | Yes | Yes | Yes (`after`/`before`) | Yes (`Type`/`Id`) | Both | Target, Channel, Operation, Status | **No** | Missing Integration Tests. |

## Documentation vs Code Review

### `docs/integration/ADMIN_READ_USAGE.md` Claims Check:
- *"Each domain provides its own set of distinct contracts, query DTOs, view DTOs, and MySQL repository implementations."* -> **Mostly true**, but `BehaviorTrace` and `DiagnosticsTelemetry` currently return their `EventDTO`s instead of a separate `ViewDTO` (e.g. `BehaviorTraceEventDTO` and `DiagnosticsTelemetryEventDTO`), and their Repositories have a `read()` method alongside `find()` which isn't mentioned.
- *"Stable cursor pagination"* -> **True**. Supported by `occurred_at`, `id` DESC logic across all `find()` methods.
- *"Safe JSON decoding"* -> **True**. Implemented gracefully across all Repositories.
- *"Domain-specific storage exceptions"* -> **True**. E.g., `AuditTrailStorageException`.

## Documentation Conflict Review

- **Conflict:** `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md` allows future "Cross-domain admin search", "Dashboard summary query contracts", and "Reporting summary query contracts".
- **Versus:** `EVENT_LOGGING_MODULE_REFERENCE.md` states "Advanced querying (generic search, UI-grids, analytics, joins) is also completely outside the scope of the package, which supports only primitive domain-specific filters."
- **Analysis:** The roadmap proposes features that seem to border on analytics or advanced querying. However, the roadmap specifically limits these to "where feasible" without a generic repository, and to "dashboard summary query contracts". Still, "cross-domain search" heavily conflicts with the strict domain isolation and "No generic log table / generic repository" rules.
- **Resolution Needed:** Before starting Phase 2, a clear architectural decision must be made on whether "cross-domain admin search" violates the "No cross-domain writer or repository" rule, or if it will be strictly implemented via a federated read service that queries each domain repository separately.

## Recommended Next Small Phase

### Phase 2: Implement Missing Integration Tests for AuditTrail Queries
- **Target Domain:** `AuditTrail` (Only one domain to start).
- **Capability:** Database Integration Tests for `AuditTrailQueryMysqlRepository`.
- **Files to change:** Add `tests/Integration/AuditTrail/Repository/AuditTrailQueryMysqlRepositoryTest.php`.
- **Reasoning:** Before defining any new DTO boundaries or query methods (as the original Phase 2 roadmap suggests), the foundation must be solid. The audit reveals 0 integration tests for the query repositories. We must prove the actual queries work against a real MySQL database. `AuditTrail` is a straightforward domain to start with.
- **Impact on Public API:** None.
- **Risks:** Low. Only adding tests.
- **Why no generic abstraction:** Tests are naturally domain-specific since the schema and repositories are domain-specific.