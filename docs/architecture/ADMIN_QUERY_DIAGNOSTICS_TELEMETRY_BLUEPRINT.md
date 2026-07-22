# DiagnosticsTelemetry Admin Query Blueprint


**Status:** Owner Approved / Runtime Authorized / Implementation Pending

## Approval Record

- Owner approval was completed on July 22, 2026.
- The approved blueprint was merged through PR #119.
- Merge commit: `201d3369c78c6f88e6bb7cba1ae0a442befb0ee2`.
- Runtime must be implemented in a separate PR from current `main`.
- No schema, Composer, CI, host, reporting, dashboard, tag, or release work is authorized.


This document defines the complete approved architecture for adding the new Admin Query API path for `DiagnosticsTelemetry`.

## 1. Classification and Strict Non-Scope Boundaries

- This is a new Admin Query API, not a rebuild.
- DiagnosticsTelemetry has no superseded post-v1 wrapper artifacts to delete.
- No schema, Composer, CI, host, factory/provider/binding, controller, route, permission, UI, export, dashboard, reporting, or framework-wiring change is authorized.
- Primitive `find()` and legacy `read()` remain separate supported paths.

## 2. Audited Baseline

- Exact audited `main` SHA: `4070718049aff7cd0b9efa9baba26673930d0ed2`
- Exact current sources reviewed:
  - Runtime contracts (`DiagnosticsTelemetryQueryInterface`, `DiagnosticsTelemetryLoggerInterface`, `DiagnosticsTelemetryPolicyInterface`)
  - Policy (`DiagnosticsTelemetryDefaultPolicy`)
  - DTOs (`DiagnosticsTelemetryQueryDTO`, `DiagnosticsTelemetryCursorDTO`, `DiagnosticsTelemetryEventDTO`, `DiagnosticsTelemetryContextDTO`)
  - Schema (`schema.maa_event_logging_diagnostics_telemetry.sql`)
  - Factory/binding (`DiagnosticsTelemetryFactory`, DI definitions)
  - Unit and Integration tests (e.g., `DiagnosticsTelemetryRepositoryTest`)
  - Standards (`PACKAGE_BUILDING_STANDARD.md`)
  - Architecture (`ADMIN_QUERY_API_ARCHITECTURE.md`)
  - Roadmap (`ADMIN_QUERY_API_ROADMAP.md`)
  - Inventory (`DOCUMENTATION_INVENTORY.md`)
- Explicit separation: The protected `v1.0.0` primitive Runtime and the approved new Admin Query API are explicitly separated. The Admin Query API does not replace or alter the protected primitive paths.

## 3. Protected Primitive Contract

The exact current primitive contract must be perfectly preserved:

- Exact signatures and contracts:
  - `public function find(DiagnosticsTelemetryQueryDTO $query): array;` (throws `DiagnosticsTelemetryStorageException`, returns `array<DiagnosticsTelemetryEventDTO>`)
  - `public function read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100): iterable;` (returns `iterable<DiagnosticsTelemetryEventDTO>`)
- Exact `DiagnosticsTelemetryQueryDTO` contract:
  - Constructor signature, types/defaults, and exact serialization order:
    ```php
    public function __construct(
        public ?\DateTimeImmutable $after = null,
        public ?\DateTimeImmutable $before = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $eventKey = null,
        public ?string $severity = null,
        public ?string $requestId = null,
        public ?string $correlationId = null,
        public ?\DateTimeImmutable $cursorOccurredAt = null,
        public ?int $cursorId = null,
        public int $limit = 50
    )
    ```
  - `DATE_ATOM` behavior: `after`, `before`, and `cursorOccurredAt` serialize using `DATE_ATOM`.
- Exact `DiagnosticsTelemetryCursorDTO` contract:
  - Constructor signature:
    ```php
    public function __construct(
        public DateTimeImmutable $lastOccurredAt,
        public int $lastId
    )
    ```
  - Serialized exactly as `lastOccurredAt`, then `lastId`, with `lastOccurredAt` using `DATE_ATOM`.
- Exact `DiagnosticsTelemetryEventDTO` contract:
  - Constructor signature, types, and exact serialization order:
    ```php
    public function __construct(
        public int $id,
        public string $eventId,
        public string $eventKey,
        public DiagnosticsTelemetrySeverityInterface $severity,
        public DiagnosticsTelemetryContextDTO $context,
        public ?int $durationMs,
        public ?array $metadata
    )
    ```
- Exact `DiagnosticsTelemetryContextDTO` contract:
  - Constructor signature, types, and exact serialization order:
    ```php
    public function __construct(
        public DiagnosticsTelemetryActorTypeInterface $actorType,
        public ?int $actorId,
        public ?string $correlationId,
        public ?string $requestId,
        public ?string $routeName,
        public ?string $ipAddress,
        public ?string $userAgent,
        public DateTimeImmutable $occurredAt
    )
    ```
  - `actorType` and `severity` (in `DiagnosticsTelemetryEventDTO`) serialize using nested enum `value()`.
  - `occurredAt` serializes using `DATE_ATOM`.
- Exact primitive repository constructor: `public function __construct(private readonly PDO $pdo, ?DiagnosticsTelemetryPolicyInterface $policy = null)`. Default policy behavior is preserved.
- Every primitive filter-to-SQL mapping:
  - `actor_type = :actor_type`
  - `actor_id = :actor_id`
  - `event_key = :event_key`
  - `severity = :severity`
  - `request_id = :request_id`
  - `correlation_id = :correlation_id`
  - `occurred_at >= :after`
  - `occurred_at <= :before`
- Cursor activation: The primitive `find()` cursor is activated only when both `cursorOccurredAt` and `cursorId` are non-null.
- Protected `find()` query behaviors: `SELECT *`, `max(1, limit)`, descending order (`ORDER BY occurred_at DESC, id DESC`), row iteration via `PDO::FETCH_ASSOC`, and all exact hydration fallbacks.
- Exact legacy `read()` cursor behavior:
  - Base query: `SELECT * FROM maa_event_logging_diagnostics_telemetry WHERE 1=1`
  - When cursor exists:
    ```sql
    AND (
        occurred_at > :last_occurred_at
        OR (
            occurred_at = :last_occurred_at_eq
            AND id > :last_id
        )
    )
    ```
  - Order and limit: `ORDER BY occurred_at ASC, id ASC LIMIT :limit`
  - Parameters: `:last_occurred_at`, `:last_occurred_at_eq`, `:last_id`, `:limit`
  - Do not apply the primitive `find()` placeholder correction to `read()`.
- Exact query/read/mapping exception prefixes and preservation of original throwable as `previous`:
  ```text
  find PDO:    Failed to query DiagnosticsTelemetry records:
  find mapper: Failed to map DiagnosticsTelemetry row:
  read PDO:    Failed to read telemetry logs:
  read mapper: Failed to map telemetry row:
  ```
- Caller-owned transaction preservation: the read repositories must not start, commit, or rollback transactions.

The distinct-placeholder correction (see Section 8) applies only to primitive `find()`; all other observable primitive behavior must remain unchanged.

## 4. Approved Admin Public Contracts

- Full interface:
  ```php
  namespace Maatify\EventLogging\DiagnosticsTelemetry\Contract;

  use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminQueryRequestDTO;
  use Maatify\EventLogging\DiagnosticsTelemetry\DTO\DiagnosticsTelemetryAdminPageResultDTO;
  use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryInvalidArgumentException;
  use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryExecutionException;
  use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException;

  interface DiagnosticsTelemetryAdminQueryInterface
  {
      /**
       * @throws DiagnosticsTelemetryAdminQueryInvalidArgumentException
       * @throws DiagnosticsTelemetryAdminQueryExecutionException
       * @throws DiagnosticsTelemetryStorageException
       */
      public function paginate(DiagnosticsTelemetryAdminQueryRequestDTO $request): DiagnosticsTelemetryAdminPageResultDTO;
  }
  ```
- Exact `DiagnosticsTelemetryAdminQueryRequestDTO` contract:
  ```php
  final readonly class DiagnosticsTelemetryAdminQueryRequestDTO implements JsonSerializable
  {
      public ?string $actorType;
      public ?int $actorId;
      public ?string $eventKey;
      public ?string $severity;
      public ?string $requestId;
      public ?string $correlationId;
      public ?DateTimeImmutable $after;
      public ?DateTimeImmutable $before;
      public int|string|null $page;
      public int|string|null $perPage;
      public ?string $sortBy;
      public ?string $sortDirection;

      public function __construct(
          ?string $actorType = null,
          ?int $actorId = null,
          ?string $eventKey = null,
          ?string $severity = null,
          ?string $requestId = null,
          ?string $correlationId = null,
          ?DateTimeImmutable $after = null,
          ?DateTimeImmutable $before = null,
          int|string|null $page = null,
          int|string|null $perPage = null,
          ?string $sortBy = null,
          ?string $sortDirection = null,
      ) {
          // Exact normalization/validation rules defined by the blueprint.
      }
  }
  ```
  - Constructor assigns the normalized/validated values exactly once to the declared readonly properties.
  - Exact request serialization keys/order match the declared property order exactly. Dates serialize using `DATE_ATOM`.
- The only Admin filters are: `actorType`, `actorId`, `eventKey`, `severity`, `requestId`, `correlationId`, `after`, `before`.
- Explicitly prohibit in this phase: `eventId`, `routeName`, `durationMs`, metadata search, free-text search, arbitrary SQL, and generic filtering. These excluded values may still appear in returned `DiagnosticsTelemetryEventDTO` items where applicable.
- Explicit string maximums (calculated by native `preg_match_all('/./us', $value)` without `ext-mbstring`):
  - `actorType`: 32
  - `eventKey`: 255
  - `severity`: 16
  - `requestId`: 64
  - `correlationId`: 36
  - `sortBy`: 64
  - `sortDirection`: 4
- Validation rules:
  - UTF-8 validation natively.
  - Trimming of string inputs.
  - Empty strings normalize to `null`.
  - Positive-ID rule for `actorId` (must be > 0).
  - `actorType` and `actorId` are independent: type-only, ID-only, both, and neither are valid. `actorId` does not require `actorType`.
  - `after` must be less than or equal to `before` (inclusive/equal date rule).
  - `DATE_ATOM` serialization behavior for dates.
  - Exact invalid sort fallback behavior:
    ```text
    sortBy occurred_at -> occurred_at
    sortBy id/other short value -> null
    overlong/invalid UTF-8 -> validation exception
    sortDirection asc/desc -> ASC/DESC
    other short value -> null
    overlong/invalid UTF-8 -> validation exception
    ```
- Exact `DiagnosticsTelemetryAdminPageResultDTO` contract:
  ```php
  /** @implements \IteratorAggregate<int, DiagnosticsTelemetryEventDTO> */
  final readonly class DiagnosticsTelemetryAdminPageResultDTO implements \IteratorAggregate, \JsonSerializable
  {
      /** @param list<DiagnosticsTelemetryEventDTO> $items */
      public function __construct(
          public array $items,
          public int $page,
          public int $perPage,
          public int $total,
          public int $filtered,
          public int $totalPages,
          public bool $hasNext,
          public bool $hasPrevious,
          public string $sortBy,
          public string $sortDirection,
      ) {
      }

      public function getIterator(): \ArrayIterator
      {
          return new \ArrayIterator($this->items);
      }

      public function jsonSerialize(): array
      {
          return [
              'items' => $this->items,
              'page' => $this->page,
              'perPage' => $this->perPage,
              'total' => $this->total,
              'filtered' => $this->filtered,
              'totalPages' => $this->totalPages,
              'hasNext' => $this->hasNext,
              'hasPrevious' => $this->hasPrevious,
              'sortBy' => $this->sortBy,
              'sortDirection' => $this->sortDirection,
          ];
      }
  }
  ```
  - State explicitly: there is no root-level `id` field in the page result DTO itself.

## 5. Policy-Aware Mapper and Repository Architecture

The primitive repository is policy-aware. To resolve this for the Admin Query API:

- Shared mapper constructor: The internal `DiagnosticsTelemetryRowMapper` must accept `DiagnosticsTelemetryPolicyInterface` in its constructor.
- Preservation of custom policy behavior: The primitive repository (`DiagnosticsTelemetryQueryMysqlRepository`) must retain its exact current constructor and pass the resolved policy to the shared mapper.
- Admin public repository constructor: `DiagnosticsTelemetryAdminQueryMysqlRepository` must have the signature:
  ```php
  public function __construct(
      private readonly \PDO $pdo,
      ?DiagnosticsTelemetryPolicyInterface $policy = null
  )
  ```
  It resolves the effective default policy if none is provided, following the established policy-aware BehaviorTrace precedent.
- Mapper/descriptor/paginator construction internally: The Admin repository must privately instantiate the `DiagnosticsTelemetryRowMapper`, `Pagination\DiagnosticsTelemetryAdminQueryDescriptorBuilder`, and `Maatify\Persistence\Pdo\Pagination\PdoPaginator`. No paginator or testing seam may be injected publicly.
- Exact mapper fallback behavior:
  ```text
  id: numeric -> int, otherwise 0
  event_id: string -> value, otherwise ''
  event_key: string -> value, otherwise 'unknown'
  severity: string -> value, otherwise 'INFO', then effective policy normalization
  actor_type: string -> value, otherwise 'ANONYMOUS', then effective policy normalization
  actor_id: numeric -> int, otherwise null
  correlation_id/request_id/route_name/ip_address/user_agent: string, otherwise null
  duration_ms: numeric -> int, otherwise null
  metadata: non-empty string decoded to any array -> array; missing/non-string/empty/malformed/scalar -> null
  occurred_at: string, otherwise epoch text; parse in UTC; invalid date text throws
  ```
  - valid decoded JSON is accepted whenever `is_array($decoded)` is true; numeric-key arrays are currently accepted and must not be changed to `null`;
  - `duration_ms` uses `is_numeric()` and casts to `int`; numeric strings are accepted and must not be reclassified as `null`.
- Exact translation of mapper failures:
  - `DiagnosticsTelemetryRowMapper::map()` throws raw mapping/policy exceptions; it does not create package storage exceptions itself.
  - primitive `find()` translates mapper exceptions using `Failed to map DiagnosticsTelemetry row: {message}`;
  - legacy `read()` translates mapper exceptions using `Failed to map telemetry row: {message}`;
  - Admin repository translates mapper/policy exceptions using `Failed to map DiagnosticsTelemetry row: {message}`;
  - every translation preserves `previous`;
  - a `DiagnosticsTelemetryStorageException` already produced by the dedicated Admin mapping callback propagates unchanged and is not double-wrapped as a paginator or execution failure.

## 6. SQL, Pagination, and Exceptions

- **Exact total SQL**:
  `SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry`
- **Exact filtered-count SQL**:
  `SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry WHERE <conditions>`
- **Exact explicit 14-column data selection**:
  `SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry WHERE <conditions>`
- **Exact filter-to-column mapping**:
  ```text
  actorType      -> actor_type = :actor_type      / actor_type
  actorId        -> actor_id = :actor_id          / actor_id
  eventKey       -> event_key = :event_key        / event_key
  severity       -> severity = :severity          / severity
  requestId      -> request_id = :request_id      / request_id
  correlationId  -> correlation_id = :correlation_id / correlation_id
  after          -> occurred_at >= :after         / after
  before         -> occurred_at <= :before        / before
  ```
- **Parameter handling**:
  - UTC conversion and `Y-m-d H:i:s.u` database formatting are performed exclusively in the descriptor builder.
  - Parameter keys in the descriptor array must not have leading colons (e.g., `['actor_type' => $val]`).
  - An empty filter set produces no `WHERE` clause.
  - Filtered-count and data SQL append the exact same generated `whereSql` (prefix exactly one ` WHERE ` and join conditions with ` AND `) and use the exact same params.
- **Pagination constraints**:
  - No `ORDER BY`, `LIMIT`, or `OFFSET` clauses inside the descriptor data SQL.
  - Canonical pagination values: default per-page 20, minimum 1, maximum 200.
  - Default sorting: `occurred_at DESC`.
  - Tie-breaker: `id DESC` (deterministic).
- **Admin repository execution flow and exception mapping**:
  - `PaginationExecutionException | PDOException` -> `DiagnosticsTelemetryStorageException` with `Failed to query DiagnosticsTelemetry records: {message}`;
  - `InvalidPaginationConfigurationException | InvalidPaginationQueryException` -> `DiagnosticsTelemetryAdminQueryExecutionException::executionFailed(...)`;
  - mapper/policy exception -> `DiagnosticsTelemetryStorageException` with `Failed to map DiagnosticsTelemetry row: {message}`;
  - storage failures never become Admin execution exceptions.
- **Exception inheritance and naming**:
  - `DiagnosticsTelemetryAdminQueryInvalidArgumentException` extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException` and implements `EventLoggingExceptionInterface`. It uses the following named factories:
    ```text
    invalidId(field)
    Invalid DiagnosticsTelemetry Admin Query ID: {field}

    invalidLength(field)
    Invalid DiagnosticsTelemetry Admin Query length: {field}

    invalidEncoding(field)
    Invalid DiagnosticsTelemetry Admin Query UTF-8 encoding: {field}

    invalidDateRange()
    Invalid DiagnosticsTelemetry Admin Query date range: after must be before or equal to before
    ```
  - `DiagnosticsTelemetryAdminQueryExecutionException` extends `Maatify\Exceptions\Exception\System\SystemMaatifyException` and implements `EventLoggingExceptionInterface`. It uses `ErrorCodeEnum::MAATIFY_ERROR` and preserves `previous`:
    ```text
    executionFailed(Throwable $previous)
    DiagnosticsTelemetry Admin Query execution failed: {previous message}
    ```

## 7. Approved File and Test Inventory

The exact expected list of files for the later Runtime implementation:

### Public Contracts
- `src/DiagnosticsTelemetry/Contract/DiagnosticsTelemetryAdminQueryInterface.php`
- `src/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminQueryRequestDTO.php`
- `src/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminPageResultDTO.php`
- `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryAdminQueryInvalidArgumentException.php`
- `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryAdminQueryExecutionException.php`

### Infrastructure
- `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryAdminQueryMysqlRepository.php`
- `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryRowMapper.php` (Internal)
- `src/DiagnosticsTelemetry/Infrastructure/Mysql/Pagination/DiagnosticsTelemetryAdminQueryDescriptorBuilder.php` (Internal)

### Primitive Adjustments
- `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryQueryMysqlRepository.php` (Update primitive cursor placeholders and use internal shared mapper)

### Unit Tests
- `tests/Unit/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminQueryRequestDTOTest.php`
- `tests/Unit/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminPageResultDTOTest.php`
- `tests/Unit/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryAdminQueryInvalidArgumentExceptionTest.php`
- `tests/Unit/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryAdminQueryExecutionExceptionTest.php`
- `tests/Unit/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryRowMapperTest.php`
- `tests/Unit/DiagnosticsTelemetry/Infrastructure/Mysql/Pagination/DiagnosticsTelemetryAdminQueryDescriptorBuilderTest.php`
- `tests/Unit/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryAdminQueryMysqlRepositoryTest.php` (Unit coverage for execution/exception mapping)

### Regression & Integration Tests
- `tests/Regression/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryQueryMysqlRepositoryRegressionTest.php` (Coverage for find and read)
- `tests/Integration/DiagnosticsTelemetry/DiagnosticsTelemetryAdminQueryMysqlRepositoryTest.php` (Strict native-MySQL Admin integration coverage)
- `tests/Integration/DiagnosticsTelemetry/DiagnosticsTelemetryRepositoryTest.php` (Update strict native-MySQL primitive compatibility coverage)

### Documentation
- `EVENT_LOGGING_PACKAGE_REFERENCE.md`
- `docs/integration/ADMIN_READ_USAGE.md`
- `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`
- `docs/audits/DOCUMENTATION_INVENTORY.md`
- `src/DiagnosticsTelemetry/README.md`
- `CHANGELOG.md`

### Strict MySQL Contract
- Missing or empty `EVENT_LOGGING_TEST_MYSQL_DSN` must fail the suite with a `RuntimeException`.
- No `markTestSkipped()` is allowed in the DiagnosticsTelemetry strict Integration paths.
- MySQL `PDO` must be strictly configured with `PDO::ATTR_EMULATE_PREPARES => false`, `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, and `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`.
- Integration tests must gate: exact cursor behavior, microsecond formatting, transaction preservation, metadata hydration, policy behavior, count/data semantic alignment, sorting mechanics, pagination boundaries, and previous-throwable wrapping.

### Later Runtime Sequence
The later Runtime PR must follow these reviewed stages:
1. public contracts, DTO validation/serialization, and exceptions;
2. policy-aware mapper and descriptor builder;
3. Admin MySQL repository and Unit exception/execution gates;
4. primitive `find()` distinct-placeholder correction plus complete `find()`/legacy `read()` Regression gates;
5. strict native-MySQL Admin and primitive Integration gates;
6. final package/integration/domain documentation and full verification.

## 8. Protected Primitive Correction Details

The distinct-placeholder correction must be applied to the primitive `find()` query (which currently reuses `:cursor_at`). It must use distinct native-PDO placeholders, for example:

```sql
(
    occurred_at < :cursor_at_before
    OR (
        occurred_at = :cursor_at_equal
        AND id < :cursor_id
    )
)
```

The exact same six-digit timestamp is bound to both placeholders.
