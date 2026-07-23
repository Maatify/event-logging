# Admin Query API Phase 1 Runtime Compatibility Inventory - DeliveryOperations

**Status:** Discovery and Audit Baseline
**Verdict:** AUDIT COMPLETE - READY FOR BLUEPRINT DESIGN

## 1. Audited State

- **Date:** 2026-07-23
- **Expected SHA:** `3d6abd502d7d82ac05828ac0beb2066e3dfc35d0`
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
- `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsLoggerMysqlRepository.php` - Implementation detail
- `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsQueryMysqlRepository.php` - Implementation detail
- `src/DeliveryOperations/README.md` - Documentation
- `src/DeliveryOperations/Recorder/DeliveryOperationsDefaultPolicy.php` - Implementation detail (Policy)
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

### Factories and Bindings
- `src/Factory/DeliveryOperationsFactory.php`
- `src/Provider/EventLoggingProvider.php`
- `src/Provider/EventLoggingProviderFactory.php`
- `src/Bootstrap/EventLoggingBindings.php`

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
- **Rules:** Limits have a default of 50. Positive/non-negative identifier constraints are handled directly in the query without throwing validation errors at instantiation.
- Serializes keys: `after`, `before`, `actorType`, `actorId`, `targetType`, `targetId`, `channel`, `operationType`, `status`, `requestId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`.
- Empty strings normalize implicitly or are allowed based on strict PDO binds.

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
- **Selected Columns:** Explicitly avoids `SELECT *` by mapping directly from PDO `FETCH_ASSOC`.

### Implementation: `DeliveryOperationsQueryMysqlRepository`
- **Constructor:** `public function __construct(private readonly PDO $pdo)`
- **Filters:** independent bindings for `actor_type`, `actor_id`, `target_type`, `target_id`, `channel`, `operation_type`, `status`, `request_id`, `correlation_id`, `after`, `before`.
- **Cursor Logic:** `< cursor_at OR (= cursor_at AND id < cursor_id)`.
- **Sort Order:** `occurred_at DESC, id DESC`.
- **Limit Rules:** Uses `max(1, $query->limit)`.
- **Placeholders:** Employs distinct bindings preventing PDO named-parameter reuse issues.
- **Hydration:** Replaces corrupt JSON with `null`. Non-array JSON parsed safely.
- **Exception Boundary:** Caught `PDOException` wraps into `DeliveryOperationsStorageException` ('Failed to query DeliveryOperations records: ' or 'Failed to map DeliveryOperations row: '). Exception traces are preserved (`previous` throwable).

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
  - Normalizes allowed actor types ('SYSTEM', 'ADMIN', 'USER', 'SERVICE', 'API_CLIENT', 'ANONYMOUS'). Uppercases inputs. Max metadata size 64KB.
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
- `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `event_id` CHAR(36) NOT NULL UNIQUE. Retained for lookups.
- `channel` VARCHAR(32) NOT NULL
- `operation_type` VARCHAR(64) NOT NULL
- `actor_type` VARCHAR(32) NULL, `actor_id` BIGINT NULL
- `target_type` VARCHAR(64) NULL, `target_id` BIGINT NULL
- `status` VARCHAR(32) NOT NULL
- `attempt_no` INT UNSIGNED NOT NULL DEFAULT 0. Relevance: Useful for tracking retries.
- `scheduled_at`, `completed_at` DATETIME(6) NULL. Relevance: Optional lifecycle timestamps.
- `correlation_id` CHAR(36) NULL, `request_id` VARCHAR(64) NULL
- `provider` VARCHAR(64) NULL, `provider_message_id` VARCHAR(128) NULL. Relevance: Optional external delivery identifiers.
- `error_code` VARCHAR(64) NULL, `error_message` TEXT NULL. Relevance: Best-effort failure details.
- `metadata` JSON NOT NULL. Relevance: Additional structured data.
- `occurred_at` DATETIME(6) NOT NULL

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

- Searched `src/DeliveryOperations` and `tests/`.
- No `AdminQuery`, `PaginatedQuery`, `PdoPaginator`, or page artifacts discovered.
- **Conclusion:** No superseded post-v1 pagination experiment or partial implementation exists.

## 7. Current Test Evidence and Gaps

| Behavior | Status |
| --- | --- |
| Empty result | PROVEN |
| Every independent filter | PROVEN |
| Combined filters | PROVEN |
| Actor type-only and ID-only | PROVEN |
| Target type-only and ID-only | PROVEN |
| Inclusive date boundaries | PROVEN |
| Exact microseconds | PROVEN |
| Cursor ordering | PROVEN |
| Limit normalization | PROVEN |
| Corrupt/scalar/numeric-array JSON | PROVEN |
| Invalid enum-like persisted values | NOT APPLICABLE |
| Storage failure translation | PROVEN |
| Caller-owned transaction preservation | NOT APPLICABLE |
| Native PDO named-placeholder compatibility | PROVEN |
| Custom policy hydration | PROVEN |

## 8. Host-Usage Search

- Repositories searched: None (Simulated environment; inaccessible host repositories).
- Result: Unable to independently prove external usage, assume protected until evidence suggests otherwise.

## 9. Blueprint Decision Matrix

| Question | Status |
| --- | --- |
| Admin Query public interface name | EVIDENCE DETERMINES |
| Request DTO fields | EVIDENCE DETERMINES |
| Page-result DTO fields and serialization order | EVIDENCE DETERMINES |
| Actor type and actor ID independence | EVIDENCE DETERMINES |
| Target type and target ID independence | EVIDENCE DETERMINES |
| Approved Admin filters | OWNER DECISION REQUIRED |
| Is `eventId` filterable? | OWNER DECISION REQUIRED |
| Provider/attempt filters included? | OWNER DECISION REQUIRED |
| Scheduled/completed timestamps as filters vs. output | OWNER DECISION REQUIRED |
| Allowed sort fields | EVIDENCE DETERMINES |
| Selected column list | EVIDENCE DETERMINES |
| Mapper and policy reuse | EVIDENCE DETERMINES |
| Pagination ownership by `maatify/persistence` | EVIDENCE DETERMINES |
| Exception translation | EVIDENCE DETERMINES |
| Strict MySQL test matrix | EVIDENCE DETERMINES |
| Schema-change requirement | EVIDENCE DETERMINES (No change needed) |

## 10. Recommended Blueprint Scope

**AUDIT RECOMMENDATION — NOT OWNER APPROVAL**

The recommended scope for the upcoming Blueprint is to define `DeliveryOperationsAdminQueryInterface`, `DeliveryOperationsAdminQueryRequestDTO`, `DeliveryOperationsAdminQueryPageResultDTO`, and a `DeliveryOperationsAdminQueryMysqlRepository`. The Repository must use `PdoPaginator` from `maatify/persistence` to provide offset-based pagination while preserving the domain exception boundary. The exact allowed filters and sort mechanisms require Owner approval. No changes should be made to the schema or primitive write/query logic.
