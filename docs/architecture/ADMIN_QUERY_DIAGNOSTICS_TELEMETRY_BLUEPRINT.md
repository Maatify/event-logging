# DiagnosticsTelemetry Admin Query Blueprint

**Status:** Proposed / Owner Review Required / Runtime Not Authorized

This document defines the complete proposed architecture for adding the new Admin Query API path for `DiagnosticsTelemetry`.

## 1. Audited Baseline

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
- Explicit separation: The protected `v1.0.0` primitive Runtime and the proposed new Admin Query API are explicitly separated. The Admin Query API does not replace or alter the protected primitive paths.

## 2. Protected Primitive Contract

The exact current primitive contract must be perfectly preserved:

- Exact signatures and contracts:
  - `public function find(DiagnosticsTelemetryQueryDTO $query): array;` (throws `DiagnosticsTelemetryStorageException`, returns `array<DiagnosticsTelemetryEventDTO>`)
  - `public function read(?DiagnosticsTelemetryCursorDTO $cursor, int $limit = 100): iterable;` (returns `iterable<DiagnosticsTelemetryEventDTO>`)
- Exact `DiagnosticsTelemetryQueryDTO` contract:
  - Constructor order: `?DateTimeImmutable $after = null, ?DateTimeImmutable $before = null, ?string $actorType = null, ?int $actorId = null, ?string $eventKey = null, ?string $severity = null, ?string $correlationId = null, ?string $requestId = null, ?DateTimeImmutable $cursorOccurredAt = null, ?int $cursorId = null, int $limit = 50`
  - Serialized keys/order: exactly matches constructor names.
  - `DATE_ATOM` behavior: `after`, `before`, and `cursorOccurredAt` serialize using `DATE_ATOM`.
- Exact `DiagnosticsTelemetryEventDTO` and `DiagnosticsTelemetryContextDTO` constructor and serialization contracts are preserved exactly as in `v1.0.0`.
- Exact primitive repository constructor: `public function __construct(private readonly PDO $pdo, ?DiagnosticsTelemetryPolicyInterface $policy = null)`. Default policy behavior is preserved.
- Every `find()` filter (`after`, `before`, `actorType`, `actorId`, `eventKey`, `severity`, `correlationId`, `requestId`) and SQL mapping must remain unchanged.
- Cursor activation: The primitive `find()` cursor is activated only when both `cursorOccurredAt` and `cursorId` are non-null.
- Protected `find()` query behaviors: `SELECT *`, `max(1, limit)`, descending order (`ORDER BY occurred_at DESC, id DESC`), row iteration via `PDO::FETCH_ASSOC`, and all exact hydration fallbacks.
- Exact legacy `read()` cursor behavior: SQL direction (`occurred_at >` or `occurred_at = AND id >`), ascending order (`ORDER BY occurred_at ASC, id ASC`), limit behavior (`LIMIT` placeholder), and exception boundaries.
- Exact query/read/mapping exception prefixes (e.g., `Failed to query DiagnosticsTelemetry records:`) and preservation of original throwable as `previous`.
- Caller-owned transaction preservation: the read repositories must not start, commit, or rollback transactions.

The distinct-placeholder correction (see Section 7) applies only to primitive `find()`; all other observable primitive behavior must remain unchanged.

## 3. Proposed Admin Public Contracts

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
- Exact `DiagnosticsTelemetryAdminQueryRequestDTO` constructor:
  ```php
  public function __construct(
      public readonly ?string $actorType = null,
      public readonly ?int $actorId = null,
      public readonly ?string $eventKey = null,
      public readonly ?string $severity = null,
      public readonly ?string $requestId = null,
      public readonly ?string $correlationId = null,
      public readonly ?string $after = null,
      public readonly ?string $before = null,
      public readonly ?string $sortBy = null,
      public readonly ?string $sortDirection = null,
      public readonly ?int $page = null,
      public readonly ?int $perPage = null,
  )
  ```
- Exact request serialization keys/order match constructor exactly.
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
  - `after` must be less than or equal to `before` (inclusive/equal date rule).
  - `DATE_ATOM` serialization behavior for dates.
  - Exact invalid sort fallback behavior: Short unknown sort values natively map to `null` without throwing an exception.
- Exact `DiagnosticsTelemetryAdminPageResultDTO` constructor/serialization:
  - Implements `IteratorAggregate`, `JsonSerializable`.
  - Serialized properties in exact order: `items, page, perPage, total, filtered, totalPages, hasNext, hasPrevious, sortBy, sortDirection`.
  - State explicitly: there is no root-level `id` field in the page result DTO itself.

## 4. Policy-Aware Mapper and Repository Architecture

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
  - Non-array rows are skipped.
  - JSON mapping for `metadata` and context: missing, non-string, empty, malformed, scalar, or numeric-key arrays map to `null`; associative objects map to `array`.
  - Missing or non-string dates map to epoch UTC (1970-01-01 00:00:00).
  - Invalid persisted date text throws an exception.
  - `durationMs` maps to null if missing or strictly not an integer.
  - Policy normalization exceptions are handled natively by the policy interface constraints.
- Exact translation of mapper failures: Mapper failures throwing `DiagnosticsTelemetryStorageException` must pass through the repository exactly as thrown, without double wrapping.

## 5. SQL, Pagination, and Exceptions

- **Exact total SQL**:
  `SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry`
- **Exact filtered-count SQL**:
  `SELECT COUNT(*) FROM maa_event_logging_diagnostics_telemetry WHERE <conditions>`
- **Exact explicit 14-column data selection**:
  `SELECT id, event_id, event_key, severity, actor_type, actor_id, correlation_id, request_id, route_name, ip_address, user_agent, duration_ms, metadata, occurred_at FROM maa_event_logging_diagnostics_telemetry WHERE <conditions>`
- **Exact filter-to-column mapping**:
  - `actorType` -> `actor_type = :actorType`
  - `actorId` -> `actor_id = :actorId`
  - `eventKey` -> `event_key = :eventKey`
  - `severity` -> `severity = :severity`
  - `requestId` -> `request_id = :requestId`
  - `correlationId` -> `correlation_id = :correlationId`
  - `after` -> `occurred_at >= :after`
  - `before` -> `occurred_at <= :before`
- **Parameter handling**:
  - UTC conversion and `Y-m-d H:i:s.u` database formatting are performed exclusively in the descriptor builder.
  - Parameter keys in the descriptor array must not have leading colons (e.g., `['actorType' => $val]`).
- **Pagination constraints**:
  - No `ORDER BY`, `LIMIT`, or `OFFSET` clauses inside the descriptor data SQL.
  - Canonical pagination values: default per-page 20, minimum 1, maximum 200.
  - Default sorting: `occurred_at DESC`.
  - Tie-breaker: `id DESC` (deterministic).
- **Admin repository execution flow and exception mapping**:
  - Invalid requests -> `DiagnosticsTelemetryAdminQueryInvalidArgumentException`.
  - Persistence configuration/query failures -> `DiagnosticsTelemetryAdminQueryExecutionException`.
  - PDO/Paginator execution failures -> `DiagnosticsTelemetryStorageException` (preserves original exception as `previous`).
  - Storage failures must never become Admin execution exceptions.
- **Exception inheritance and naming**:
  - `DiagnosticsTelemetryAdminQueryInvalidArgumentException` extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException` and implements `EventLoggingExceptionInterface`. Uses named factory `invalidRequest(...)`.
  - `DiagnosticsTelemetryAdminQueryExecutionException` extends `Maatify\Exceptions\Exception\System\SystemMaatifyException` and implements `EventLoggingExceptionInterface`. Uses named factory `paginationFailed(...)` and overrides `defaultErrorCode()` to return `ErrorCodeEnum::MAATIFY_ERROR`.

## 6. Proposed File and Test Inventory

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
- The implementation, primitive distinct-placeholder correction, strict Integration evidence, and final documentation updates must all be submitted and reviewed as a single atomic PR before merge.

## 7. Protected Primitive Correction Details

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
