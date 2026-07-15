# AuthoritativeAudit Admin Query Rebuild Blueprint

**Status:** Proposed / Pending Owner Approval

This document defines the complete proposed architecture for replacing the superseded post-v1 AuthoritativeAudit pagination wrapper with a package-owned Admin Query API.

It establishes the blueprint for the final remediation phase, ensuring strict fail-closed behavior, protected transaction boundaries, and separation from outbox semantics. It authorizes a separate Runtime implementation task/PR once approved, but it does **not** itself implement Runtime, tagging, or release work.

---

## 1. Audited Baseline

- **Exact audited main SHA:** `fc590f53687935d1f02d5b96782f2349de7e931a`
- **Purpose:** Rebuild post-v1 pagination experiment into Admin Query API architecture.

### 1.1 Protected `v1.0.0` Contract

The following primitive contracts are protected and preserved:

- Exact primitive interface signature and return docblock:
  ```php
  /**
   * @return array<AuthoritativeAuditViewDTO>
   * @throws AuthoritativeAuditStorageException
   */
  public function find(AuthoritativeAuditQueryDTO $query): array;
  ```

- Exact `AuthoritativeAuditQueryDTO` constructor and serialization order:
  - `after`
  - `before`
  - `actorType`
  - `actorId`
  - `targetType`
  - `targetId`
  - `action`
  - `correlationId`
  - `cursorOccurredAt`
  - `cursorId`
  - `limit` (default 50)

- Exact `AuthoritativeAuditViewDTO` constructor and serialization order:
  - `id`
  - `eventId`
  - `actorType`
  - `actorId`
  - `action`
  - `targetType`
  - `targetId`
  - `ipAddress`
  - `userAgent`
  - `correlationId`
  - `changes`
  - `occurredAt`

- Exact primitive repository constructor:
  ```php
  public function __construct(private readonly PDO $pdo)
  ```

- Query Constraints:
  - Filters are independently processed.
  - Cursor is activated only if both `cursorOccurredAt` and `cursorId` are strictly not null.
  - Limit is normalized as `max(1, $limit)`.
  - Ordering remains `occurred_at DESC, id DESC`.
  - Primitive query retains `SELECT *` from `maa_event_logging_authoritative_audit_log`.

- Processing and Hydration:
  - Non-array fetched rows are skipped (continue).
  - Explicit hydration fallbacks map:
    - missing/non-string/empty/malformed/scalar/numeric-key array for `changes` -> `null`; valid associative object -> `array`
    - missing/non-string date for `occurred_at` -> epoch UTC (`1970-01-01 00:00:00`); invalid persisted date text throws `Exception`.
  - Exact query exception: `Failed to query AuthoritativeAudit records: {message}`
  - Exact map exception: `Failed to map AuthoritativeAudit row: {message}`
  - The previous throwable must be explicitly passed.

### 1.2 Superseded Post-v1 Pagination Artifacts

The following exactly 7 files are superseded post-v1 artifacts and must be deleted atomically during implementation:
- `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`
- `src/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryService.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTO.php`
- `tests/Unit/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryServiceTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTOTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTOTest.php`

### 1.3 Exact Schema/Index Contract

Database operations strictly abide by the schema defined in `src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql`.
- No schema change is authorized.
- The `maa_event_logging_authoritative_audit_outbox` table is the absolute transactional fail-closed source of truth for writes.
- The Admin query and primitive read operations will exclusively target the materialized log `maa_event_logging_authoritative_audit_log`.

---

## 2. Target Admin Query API

### 2.1 API Components

The Admin Query API introduces the following new classes within the package, properly isolated from the primitive API:
- **Admin Interface:** `Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditAdminQueryInterface` (public)
- **Request DTO:** `Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO` (public, final readonly)
- **Page Result DTO:** `Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO` (public, final readonly)
- **Admin Repository:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository` (internal implementation, final)
- **RowMapper:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper` (internal, final)
- **DescriptorBuilder:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder` (internal, final)
- **Exceptions:**
  - `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException`
  - `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException`

### 2.2 Complete Request DTO Contract

```php
namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use JsonSerializable;

final readonly class AuthoritativeAuditAdminQueryRequestDTO implements JsonSerializable
{
    public function __construct(
        public ?string $eventId = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?string $action = null,
        public ?string $correlationId = null,
        public ?DateTimeImmutable $after = null,
        public ?DateTimeImmutable $before = null,
        public int|string|null $page = null,
        public int|string|null $perPage = null,
        public ?string $sortBy = null,
        public ?string $sortDirection = null
    ) {
    }

    public function jsonSerialize(): mixed
    {
        // Must follow exact constructor order and output keys
    }
}
```

**Normalization constraints:**
- **trim**: string inputs are trimmed.
- **empty string**: maps to `null`.
- **UTF-8 validation**: validation occurs without dependency on `mbstring`.
- **maximums limits**:
  - `eventId`: 36
  - `actorType`: 32
  - `targetType`: 64
  - `action`: 128
  - `correlationId`: 36
  - `sortBy`: 64
  - `sortDirection`: 4
- **ID validation**: `actorId` and `targetId` must be strictly positive when present (no negative, no NULL search).
- **Date boundary validation**: `after` must be <= `before`.
- **Sorting validation**:
  - `sortBy` accepts only `occurred_at`. Any other string (including `id` or short unknown values) maps to `null`.
  - `sortDirection` maps `asc`/`desc` to `ASC`/`DESC`. Any unknown short value maps to `null`.
- **Pagination mechanics passing**: `page` and `perPage` are directly passed to `maatify/persistence` without redefining their logic.
- Type-only and ID-only search requests are permitted.
- `Global Search` is strictly excluded (marked as a future unified cross-domain extension).

### 2.3 Complete Page Result DTO Contract

```php
namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, AuthoritativeAuditViewDTO>
 */
final readonly class AuthoritativeAuditAdminPageResultDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<AuthoritativeAuditViewDTO> $items
     */
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
        public string $sortDirection
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function jsonSerialize(): mixed
    {
        // Exact matching serialization keys
    }
}
```

---

## 3. Storage SQL and Exceptions Contract

### 3.1 Exact Admin SQL

- **totalSql:** Evaluates base query with no filters.
- **filteredCountSql:** Evaluates base query against filters.
- **dataSql:** Extracts paginated entries adhering to the exact selected columns:
  `id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at`
- Exact filter mappings use uniquely distinct named placeholders per usage context.
- Dates format as UTC string `Y-m-d H:i:s.u`.
- The DescriptorBuilder maintains exactly the same where clause and parameter mappings for `filteredCountSql` and `dataSql`.
- `ORDER BY`, `LIMIT` and `OFFSET` clauses are strictly excluded from the descriptor payload.

### 3.2 Pagination Configuration

- **public sort whitelist:** `occurred_at` only.
- **internal sort whitelist:** `occurred_at`, `id`.
- **default sort:** `occurred_at DESC`.
- **tie-breaker:** `id DESC` strictly constant.
- **Pagination clamping:** default 20, min 1, max 200.

### 3.3 Primitive Cursor Placeholder Fix

The repeated `:cursor_at` placeholder inside the `find` implementation is corrected behavior-preservingly:
```sql
(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))
```
Both placeholders will receive the exact same datetime string value.

### 3.4 Exception Mapping

- `Invalid request payload` → throws `AuthoritativeAuditAdminQueryInvalidArgumentException`.
- `InvalidPaginationConfigurationException` or `InvalidPaginationQueryException` → throws `AuthoritativeAuditAdminQueryExecutionException`.
- `PaginationExecutionException` or `PDOException` → throws `AuthoritativeAuditStorageException`.
- mapper `Throwable` → throws `AuthoritativeAuditStorageException`.
- The previous throwable (`$e`) must be preserved in translation.
- Existing storage exceptions thrown by internal layers (mapper) pass through unchanged (no double wrapping).

---

## 4. Exact File Inventory

### 4.1 Created:
- `src/AuthoritativeAudit/Contract/AuthoritativeAuditAdminQueryInterface.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditAdminQueryRequestDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditAdminPageResultDTO.php`
- `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepository.php`
- `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditRowMapper.php`
- `src/AuthoritativeAudit/Infrastructure/Mysql/Pagination/AuthoritativeAuditAdminQueryDescriptorBuilder.php`
- `src/AuthoritativeAudit/Exception/AuthoritativeAuditAdminQueryInvalidArgumentException.php`
- `src/AuthoritativeAudit/Exception/AuthoritativeAuditAdminQueryExecutionException.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditAdminQueryRequestDTOTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditAdminPageResultDTOTest.php`
- `tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/Pagination/AuthoritativeAuditAdminQueryDescriptorBuilderTest.php`
- `tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditRowMapperTest.php`
- `tests/Unit/AuthoritativeAudit/Exception/AuthoritativeAuditAdminQueryInvalidArgumentExceptionTest.php`
- `tests/Unit/AuthoritativeAudit/Exception/AuthoritativeAuditAdminQueryExecutionExceptionTest.php`
- `tests/Integration/AuthoritativeAudit/AuthoritativeAuditAdminQueryMysqlRepositoryTest.php`
- `tests/Integration/AuthoritativeAudit/AuthoritativeAuditQueryMysqlRepositoryTest.php`
- `tests/Regression/AuthoritativeAudit/AuthoritativeAuditQueryMysqlRepositoryRegressionTest.php`

### 4.2 Modified:
- `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditQueryMysqlRepository.php`
- `docs/integration/ADMIN_READ_USAGE.md`
- `src/AuthoritativeAudit/README.md`
- `EVENT_LOGGING_PACKAGE_REFERENCE.md`
- `CHANGELOG.md`

### 4.3 Deleted:
- `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`
- `src/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryService.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTO.php`
- `tests/Unit/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryServiceTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTOTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTOTest.php`

---

## 5. Testing Contract

Testing spans Unit, Regression, and strict real-MySQL Integration domains proving:
- DTO normalization, validation boundaries and strict serialization paths.
- All filters apply both independently and concurrently.
- Proper evaluation of `eventId` filter.
- Accurate total / filtered / data semantic alignment within pagination routines.
- Correct exact SQL descriptors generated without ORDER/LIMIT injections.
- Pagination sort normalization with static tie-breakers (id DESC).
- Page limits enforcement matching min/max thresholds.
- Safe nullable fields extraction matching constraints.
- Dedicated Unit and Regression tests handling exact corrupt JSON mapper processing logic without relying on skip mechanics in rigid schema instances.
- Realigned primitive query native PDO distinct placeholders generating exact identical downstream logic.
- Guaranteed complete preservation of all protected `v1.0.0` implementations.
- Strict mapping of translation boundaries preserving exact messages alongside their source throwables.
- Storage pass-through preventing dual wrapper exception envelopes.
- Non-interference of caller-owned transaction borders across repository implementations.
- Validation that the target dataset relies strictly on log structures without impacting failure-critical outbox boundaries.
- Full verification of tests executing under live strict MySQL integrations environments.
