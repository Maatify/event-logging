# DeliveryOperations Discovery and Compatibility Audit

**Status:** Discovery and Audit Baseline
**Verdict:** PACKAGE AUDIT COMPLETE - WITH EXPLICIT HOST-USAGE VERIFICATION GAP

## 1. Audited State

- **Date:** 2026-07-23
- **Audited main SHA:** `3d6abd502d7d82ac05828ac0beb2066e3dfc35d0`
- **Governing Documents Inspected:** `AGENTS.md`, `EVENT_LOGGING_PACKAGE_REFERENCE.md`, `CHANGELOG.md`, `docs/standards/PACKAGE_BUILDING_STANDARD.md`, `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md`, `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`, `docs/audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md`, `docs/audits/DOCUMENTATION_INVENTORY.md`
- **Released Baseline:** Tag `v1.0.0`
- **Inspected Paths:** `src/DeliveryOperations/`, `tests/Unit/DeliveryOperations/`, `tests/Integration/DeliveryOperations/`, `src/Provider/`, `src/Factory/`, `src/Bootstrap/`, `schema/`, `EVENT_LOGGING_PACKAGE_REFERENCE.md`
- **Verification Gaps:** Host repositories are inaccessible in this environment.

## 2. Complete DeliveryOperations Inventory

### Current implementation (`src/DeliveryOperations/`)
- `src/DeliveryOperations/Command/RecordDeliveryOperationCommand.php` - Protected contract
- `src/DeliveryOperations/Contract/DeliveryOperationsLoggerInterface.php` - Protected contract
- `src/DeliveryOperations/Contract/DeliveryOperationsPolicyInterface.php` - Protected contract
- `src/DeliveryOperations/Contract/DeliveryOperationsQueryInterface.php` - Protected contract
- `src/DeliveryOperations/DTO/DeliveryOperationRecordDTO.php` - Protected contract
- `src/DeliveryOperations/DTO/DeliveryOperationsQueryDTO.php` - Protected contract
- `src/DeliveryOperations/DTO/DeliveryOperationsViewDTO.php` - Protected contract
- `src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql` - Protected contract (Schema)
- `src/DeliveryOperations/Enum/DeliveryActorTypeInterface.php` - Protected contract
- `src/DeliveryOperations/Enum/DeliveryChannelEnum.php` - Protected contract
- `src/DeliveryOperations/Enum/DeliveryOperationTypeEnum.php` - Protected contract
- `src/DeliveryOperations/Enum/DeliveryStatusEnum.php` - Protected contract
- `src/DeliveryOperations/Exception/DeliveryOperationsStorageException.php` - Protected contract
- `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsLoggerMysqlRepository.php` - Protected published Runtime surface (internals may be refactored if behavior remains compatible)
- `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsQueryMysqlRepository.php` - Protected published Runtime surface (internals may be refactored if behavior remains compatible)
- `src/DeliveryOperations/README.md` - Documentation
- `src/DeliveryOperations/Recorder/DeliveryOperationsDefaultPolicy.php` - Protected published Runtime surface (internals may be refactored if behavior remains compatible)
- `src/DeliveryOperations/Recorder/DeliveryOperationsRecorder.php` - Protected contract (Write boundary)

### Tests
- `tests/Integration/DeliveryOperations/DeliveryOperationsRepositoryTest.php`
- `tests/Unit/DeliveryOperations/Command/RecordDeliveryOperationCommandTest.php`
- `tests/Unit/DeliveryOperations/DTO/DeliveryOperationRecordDTOTest.php`
- `tests/Unit/DeliveryOperations/DTO/DeliveryOperationsQueryDTOTest.php`
- `tests/Unit/DeliveryOperations/DTO/DeliveryOperationsViewDTOTest.php`
- `tests/Unit/DeliveryOperations/Recorder/DeliveryOperationsDefaultPolicyTest.php`
- `tests/Unit/DeliveryOperations/Recorder/DeliveryOperationsRecorderTest.php`
- `tests/Unit/DeliveryOperations/Repository/DeliveryOperationsLoggerMysqlRepositoryTest.php`
- `tests/Unit/DeliveryOperations/Repository/DeliveryOperationsQueryMysqlRepositoryTest.php`

### Package Reference Sections
- Sections reviewed: `6. DeliveryOperations`, `DeliveryOperationsFactory`, Delivery operations filtering/pagination capabilities, and exceptions/fail-open boundaries in `EVENT_LOGGING_PACKAGE_REFERENCE.md`.

### External Tests
- Searched entire `tests/` outside `tests/*/DeliveryOperations/` for Factory/Provider/Bindings references.
- Exact search returned no tests covering DeliveryOperations bindings outside its own domain folder.

### Factories and Bindings
- `src/Factory/DeliveryOperationsFactory.php` - Protected contract
- `src/Provider/EventLoggingProvider.php` (accessor) - Protected contract
- `src/Provider/EventLoggingProviderFactory.php` - Protected contract
- `src/Bootstrap/EventLoggingBindings.php` - Protected contract

*Note: No DeliveryOperations Regression test file exists.*

## 3. Protected Primitive Query Contract

### Interface: `DeliveryOperationsQueryInterface`
- `public function find(DeliveryOperationsQueryDTO $query): array;`
- **Exceptions:** `@throws DeliveryOperationsStorageException`

### Request DTO: `DeliveryOperationsQueryDTO`
- **Constructor:**
  ```php
  public function __construct(
      public ?\DateTimeImmutable $after = null,
      public ?\DateTimeImmutable $before = null,
      public ?string $actorType = null,
      public ?int $actorId = null,
      public ?string $targetType = null,
      public ?int $targetId = null,
      public ?string $channel = null,
      public ?string $operationType = null,
      public ?string $status = null,
      public ?string $requestId = null,
      public ?string $correlationId = null,
      public ?\DateTimeImmutable $cursorOccurredAt = null,
      public ?int $cursorId = null,
      public int $limit = 50
  )
  ```
- **Rules:** Performs no validation or normalization. Empty strings remain equality-filter values (no implicit normalization). Zero/negative actor, target, and cursor IDs are not rejected by the query DTO.
- Serializes keys: `after`, `before`, `actorType`, `actorId`, `targetType`, `targetId`, `channel`, `operationType`, `status`, `requestId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`.

### View DTO: `DeliveryOperationsViewDTO`
- Fully maps `maa_event_logging_delivery_operations` columns.
- **Constructor:**
  ```php
  public function __construct(
      public int $id,
      public string $eventId,
      public string $channel,
      public string $operationType,
      public ?string $actorType,
      public ?int $actorId,
      public ?string $targetType,
      public ?int $targetId,
      public string $status,
      public int $attemptNo,
      public ?\DateTimeImmutable $scheduledAt,
      public ?\DateTimeImmutable $completedAt,
      public ?string $correlationId,
      public ?string $requestId,
      public ?string $provider,
      public ?string $providerMessageId,
      public ?string $errorCode,
      public ?string $errorMessage,
      public ?array $metadata,
      public \DateTimeImmutable $occurredAt
  )
  ```
- Timestamps serialize using `DATE_ATOM`.
- **Selected Columns:** The repository currently uses `SELECT *`; it does **not** use an explicit selected-column list.

### Implementation: `DeliveryOperationsQueryMysqlRepository`
- **Constructor:** `public function __construct(private readonly PDO $pdo)`
- **Filters:** independent bindings for `actor_type`, `actor_id`, `target_type`, `target_id`, `channel`, `operation_type`, `status`, `request_id`, `correlation_id`, `after`, `before`.
- **Inclusive Date Boundaries:** `occurred_at >= :after` and `occurred_at <= :before`.
- **Cursor Logic:** `< cursor_at OR (= cursor_at AND id < cursor_id)`. Cursor filtering activates only when both cursor values are non-null; a partial cursor is ignored.
- **Sort Order:** `occurred_at DESC, id DESC`.
- **Limit Rules:** Behavior is `max(1, $query->limit)` with no maximum clamp.
- **Placeholders:** The cursor SQL reuses `:cursor_at` twice. Native-PDO distinct-placeholder compatibility is therefore **not proven** and the current implementation conflicts with the repository distinct-placeholder rule. This is a factual primitive implementation gap for later correction.
- **Hydration:** Corrupt JSON returns `null`. Scalar JSON and numeric-list JSON also return `null` by code behavior, but existing tests only directly prove the corrupt-JSON case. Timestamps are hydrated as UTC `DateTimeImmutable`. `channel`, `operationType`, and `status` are hydrated as raw strings, passing through unknown persisted values rather than invoking enum fallback.
- **Transactions:** The repository does not begin, commit, or roll back transactions. Caller-owned transactions are preserved by code structure, but no direct transaction test currently proves it.
- **Serialization Evidence:** DTO JSON serialization uses `DATE_ATOM` and does not preserve six-digit microseconds in serialized output. Exact microsecond preservation is not directly proven by current tests.
- **Exception Boundary:** `PDOException` maps to the `Failed to query DeliveryOperations records:` prefix. Non-PDO mapping/hydration failures map through the separate `Throwable` catch to `Failed to map DeliveryOperations row:`. Previous throwable is preserved in both cases.

## 4. Write-Side Compatibility Boundary

- **`DeliveryOperationsRecorder`:**
  - `public function record(DeliveryChannelEnum|string $channel, DeliveryOperationTypeEnum|string $operationType, DeliveryStatusEnum|string $status, int $attemptNo = 0, DeliveryActorTypeInterface|string|null $actorType = null, ?int $actorId = null, ?string $targetType = null, ?int $targetId = null, ?DateTimeImmutable $scheduledAt = null, ?DateTimeImmutable $completedAt = null, ?string $correlationId = null, ?string $requestId = null, ?string $provider = null, ?string $providerMessageId = null, ?string $errorCode = null, ?string $errorMessage = null, ?array $metadata = null): void`
  - Fail-open boundary. Catches all `Throwable` errors during validation, metadata size checking (64KB default limit), JSON encoding, or PDO log operations. Best-effort reports to PSR-3 fallback logger and does not crash the caller.
- **`DeliveryOperationsLoggerInterface`:**
  - `public function log(DeliveryOperationRecordDTO $dto): void`
- **`DeliveryOperationsLoggerMysqlRepository`:**
  - `public function __construct(private readonly PDO $pdo)`
  - Throws `DeliveryOperationsStorageException` on database write or metadata encoding failure. Message prefixes: 'Database write failed: ' and 'Metadata encoding failed: '.
- **`DeliveryOperationsPolicyInterface`:**
  - `public function normalizeActorType(DeliveryActorTypeInterface|string $actorType): string;`
  - `public function validateMetadataSize(string $json): bool;`
- **`DeliveryOperationsDefaultPolicy`:**
  - Uppercases every actor type. It recognizes the documented list ('SYSTEM', 'ADMIN', 'USER', 'SERVICE', 'API_CLIENT', 'ANONYMOUS') but does not reject or remap values outside that list. Max metadata size 64KB.
- **`DeliveryOperationsFactory`:**
  - `public static function create(PDO $pdo, ClockInterface $clock, ?LoggerInterface $psrLogger = null, ?DeliveryOperationsPolicyInterface $policy = null): DeliveryOperationsRecorder`
  - Instantiates logger repository and recorder.
- **`EventLoggingProvider` / `EventLoggingProviderFactory`:**
  - Exposes `deliveryOperations(): DeliveryOperationsRecorder`. Uses Factory.
- **`EventLoggingBindings`:**
  - Provides DI bindings for `DeliveryOperationsQueryInterface` and `DeliveryOperationsRecorder` using PHP callables.
- **Event ID Generation:** `Uuid::uuid4()->toString()`
- **Enum Handling:** Normalizes BackedEnums and UnitEnums to strings dynamically.
- **Timestamp Assignment:** Done internally.
- **Sanitization:** Truncates strings based on schema maximum lengths (e.g. 32, 64, 36, 128 characters).

## 5. Schema and Index Audit

**Table:** `maa_event_logging_delivery_operations`
- `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY (No explicit default)
- `event_id` CHAR(36) NOT NULL UNIQUE (No explicit default). Retained for lookups.
- `channel` VARCHAR(32) NOT NULL (No explicit default)
- `operation_type` VARCHAR(64) NOT NULL (No explicit default)
- `actor_type` VARCHAR(32) NULL (Effective default NULL), `actor_id` BIGINT NULL (Effective default NULL)
- `target_type` VARCHAR(64) NULL (Effective default NULL), `target_id` BIGINT NULL (Effective default NULL)
- `status` VARCHAR(32) NOT NULL (No explicit default)
- `attempt_no` INT UNSIGNED NOT NULL DEFAULT 0 (Explicit default). Relevance: Useful for tracking retries.
- `scheduled_at`, `completed_at` DATETIME(6) NULL (Effective default NULL). Relevance: Optional lifecycle timestamps.
- `correlation_id` CHAR(36) NULL (Effective default NULL), `request_id` VARCHAR(64) NULL (Effective default NULL)
- `provider` VARCHAR(64) NULL (Effective default NULL), `provider_message_id` VARCHAR(128) NULL (Effective default NULL). Relevance: Optional external delivery identifiers.
- `error_code` VARCHAR(64) NULL (Effective default NULL), `error_message` TEXT NULL (Effective default NULL). Relevance: Best-effort failure details.
- `metadata` JSON NOT NULL (No explicit default). Relevance: Additional structured data.
- `occurred_at` DATETIME(6) NOT NULL (No explicit default)

**Indices:**
- `idx_delivery_ops_time` (occurred_at, id)
- `idx_delivery_ops_actor_time` (actor_type, actor_id, occurred_at)
- `idx_delivery_ops_channel_time` (channel, occurred_at)
- `idx_delivery_ops_type_time` (operation_type, occurred_at)
- `idx_delivery_ops_status_time` (status, occurred_at)
- `idx_delivery_ops_target_time` (target_type, target_id, occurred_at)
- `idx_delivery_ops_correlation_time` (correlation_id, occurred_at)
- `idx_delivery_ops_request_time` (request_id, occurred_at)

## 6. Existing Pagination-Artifact Search

- Searched entire repository (`src/`, `tests/`, `docs/`) for `AdminQuery`, `PaginatedQuery`, `PdoPaginator`, `QueryCursorDTO`, `QueryPageDTO`, `PaginatedQueryService`, `PaginationQueryDescriptor`.
- Exact matching paths:
  - `src/AuthoritativeAudit/Contract/AuthoritativeAuditAdminQueryInterface.php` (Protected Admin Query)
  - `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepository.php` (Protected Admin Query)
  - `src/AuditTrail/Contract/AuditTrailAdminQueryInterface.php` (Protected Admin Query)
  - `src/AuditTrail/Infrastructure/Mysql/AuditTrailAdminQueryMysqlRepository.php` (Protected Admin Query)
  - `src/BehaviorTrace/Contract/BehaviorTraceAdminQueryInterface.php` (Protected Admin Query)
  - `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceAdminQueryMysqlRepository.php` (Protected Admin Query)
  - `src/DiagnosticsTelemetry/Contract/DiagnosticsTelemetryAdminQueryInterface.php` (Protected Admin Query)
  - `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryAdminQueryMysqlRepository.php` (Protected Admin Query)
  - `src/SecuritySignals/Contract/SecuritySignalsAdminQueryInterface.php` (Protected Admin Query)
  - `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsAdminQueryMysqlRepository.php` (Protected Admin Query)
  - (and associated DTOs/Tests for the above implemented domains).
- **Conclusion:** No DeliveryOperations Admin or paginated artifact exists. No superseded post-v1 pagination experiment or partial implementation exists for this domain.

## 7. Current Test Evidence and Gaps

| Behavior | Status |
| --- | --- |
| Empty result | PROVEN |
| Every independent filter | PARTIAL |
| Combined filters | PARTIAL |
| Actor type-only and ID-only | NOT PROVEN |
| Target type-only and ID-only | NOT PROVEN |
| Inclusive date boundaries | NOT PROVEN |
| Exact microseconds | NOT PROVEN |
| Cursor ordering | PARTIAL (Integration test exists but can be skipped if DB unavailable) |
| Limit normalization | NOT PROVEN |
| Corrupt/scalar/numeric-array JSON | PARTIAL (only corrupt JSON proven) |
| Invalid enum-like persisted values | NOT APPLICABLE |
| Storage failure translation | PARTIAL (PDO failure proven, hydration mapping prefix/throwable NOT fully proven directly) |
| Caller-owned transaction preservation | NOT PROVEN (applicable but not proven) |
| Native PDO named-placeholder compatibility | NOT PROVEN |
| Custom policy hydration | NOT APPLICABLE (query repository has no policy dependency) |

## 8. Host-Usage Search

- Repositories attempted: None (0 repositories searched).
- Result: Host search was unperformed. Host usage is preserved as an unresolved verification gap.

## 9. Blueprint Decision Matrix

| Question | Status |
| --- | --- |
| Admin Query public interface name | EVIDENCE DETERMINES |
| Request DTO fields | OWNER DECISION REQUIRED |
| Page-result DTO fields and serialization order | EVIDENCE DETERMINES |
| Actor type and actor ID independence | EVIDENCE DETERMINES |
| Target type and target ID independence | EVIDENCE DETERMINES |
| Approved Admin filters | OWNER DECISION REQUIRED |
| Is `eventId` filterable? | OWNER DECISION REQUIRED |
| Provider/attempt filters included? | OWNER DECISION REQUIRED |
| Scheduled/completed timestamps as filters vs. output | OWNER DECISION REQUIRED |
| Allowed sort fields | OWNER DECISION REQUIRED |
| Selected column list | EVIDENCE DETERMINES |
| Mapper and policy reuse | OWNER DECISION REQUIRED |
| Pagination ownership by `maatify/persistence` | EVIDENCE DETERMINES |
| Exception translation | EVIDENCE DETERMINES |
| Strict MySQL test matrix | EVIDENCE DETERMINES |
| Schema-change requirement | OWNER DECISION REQUIRED (Current evidence does not justify schema change, but remains unresolved until filters/sorts approved) |

## 10. Recommended Blueprint Scope

**AUDIT RECOMMENDATION — NOT OWNER APPROVAL**

The recommended scope for the upcoming Blueprint is to define `DeliveryOperationsAdminQueryInterface`, `DeliveryOperationsAdminQueryRequestDTO`, `DeliveryOperationsAdminPageResultDTO`, and a `DeliveryOperationsAdminQueryMysqlRepository`. The Repository must use `PdoPaginator` from `maatify/persistence` to provide offset-based pagination while preserving the domain exception boundary. The exact allowed filters and sort mechanisms require Owner approval. No changes should be made to the schema or primitive write/query logic.
