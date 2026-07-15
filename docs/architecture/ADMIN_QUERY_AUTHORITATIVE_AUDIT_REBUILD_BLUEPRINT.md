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
  ```php
  public function __construct(
      public ?\DateTimeImmutable $after = null,
      public ?\DateTimeImmutable $before = null,
      public ?string $actorType = null,
      public ?int $actorId = null,
      public ?string $targetType = null,
      public ?int $targetId = null,
      public ?string $action = null,
      public ?string $correlationId = null,
      public ?\DateTimeImmutable $cursorOccurredAt = null,
      public ?int $cursorId = null,
      public int $limit = 50
  ) {
  }

  public function jsonSerialize(): mixed
  {
      return [
          'after' => $this->after?->format(DATE_ATOM),
          'before' => $this->before?->format(DATE_ATOM),
          'actorType' => $this->actorType,
          'actorId' => $this->actorId,
          'targetType' => $this->targetType,
          'targetId' => $this->targetId,
          'action' => $this->action,
          'correlationId' => $this->correlationId,
          'cursorOccurredAt' => $this->cursorOccurredAt?->format(DATE_ATOM),
          'cursorId' => $this->cursorId,
          'limit' => $this->limit,
      ];
  }
  ```

- Exact `AuthoritativeAuditViewDTO` constructor and serialization order:
  ```php
  /** @param array<string, mixed>|null $changes */
  public function __construct(
      public int $id,
      public string $eventId,
      public ?string $actorType,
      public ?int $actorId,
      public string $action,
      public ?string $targetType,
      public ?int $targetId,
      public ?string $ipAddress,
      public ?string $userAgent,
      public ?string $correlationId,
      public ?array $changes,
      public \DateTimeImmutable $occurredAt
  ) {
  }

  public function jsonSerialize(): mixed
  {
      return [
          'id' => $this->id,
          'eventId' => $this->eventId,
          'actorType' => $this->actorType,
          'actorId' => $this->actorId,
          'action' => $this->action,
          'targetType' => $this->targetType,
          'targetId' => $this->targetId,
          'ipAddress' => $this->ipAddress,
          'userAgent' => $this->userAgent,
          'correlationId' => $this->correlationId,
          'changes' => $this->changes,
          'occurredAt' => $this->occurredAt->format(DATE_ATOM),
      ];
  }
  ```

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
    - `id`: fallback to 0.
    - `event_id`: fallback to empty string `''`.
    - `action`: fallback to empty string `''`.
    - `actor_type`, `actor_id`, `target_type`, `target_id`, `ip_address`, `user_agent`, `correlation_id`: explicit strict types extracted, otherwise mapped to `null`.
    - `changes` JSON extraction: missing/non-string/empty/malformed/scalar/numeric-key array -> `null`. Associative JSON object -> `array`.
    - `occurred_at` date value: missing/non-string -> epoch UTC (`1970-01-01 00:00:00`).
    - Invalid persisted date text throws an exception during `DateTimeImmutable` construction.
  - Exact query exception catch boundaries (PDOException): `Failed to query AuthoritativeAudit records: {message}` with previous throwable.
  - Exact map exception catch boundaries (Throwable): `Failed to map AuthoritativeAudit row: {message}` with previous throwable.

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

Database operations strictly abide by the schema defined in `src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql`:
- **maa_event_logging_authoritative_audit_outbox** (InnoDB):
  - `id` BIGINT AUTO_INCREMENT PRIMARY KEY
  - `event_id` CHAR(36) NOT NULL
  - `actor_type` VARCHAR(32) NULL
  - `actor_id` BIGINT NULL
  - `action` VARCHAR(128) NOT NULL
  - `target_type` VARCHAR(64) NULL
  - `target_id` BIGINT NULL
  - `ip_address` VARCHAR(45) NULL
  - `user_agent` TEXT NULL
  - `correlation_id` VARCHAR(36) NULL
  - `changes` JSON NULL
  - `occurred_at` DATETIME(6) NOT NULL
  - UNIQUE KEY `uq_authoritative_audit_outbox_event` (`event_id`)
- **maa_event_logging_authoritative_audit_log** (InnoDB):
  - (Exact same column definitions as outbox table)
  - UNIQUE KEY `uq_authoritative_audit_log_event` (`event_id`)
  - INDEX `idx_authoritative_audit_log_actor` (`actor_type`, `actor_id`)
  - INDEX `idx_authoritative_audit_log_target` (`target_type`, `target_id`)
  - INDEX `idx_authoritative_audit_log_occurred` (`occurred_at`)
  - INDEX `idx_authoritative_audit_log_action` (`action`)
  - INDEX `idx_authoritative_audit_log_correlation` (`correlation_id`)
- No schema change is authorized.
- The `maa_event_logging_authoritative_audit_outbox` table is the absolute transactional fail-closed source of truth for writes.
- The Admin query and primitive read operations will exclusively target the materialized log `maa_event_logging_authoritative_audit_log`.

---

## 2. Target Admin Query API

### 2.1 API Components

The Admin Query API introduces the following new classes within the package, properly isolated from the primitive API:
- **Admin Interface:** `Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditAdminQueryInterface` (public interface declaring `paginate`)
- **Request DTO:** `Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO` (public, final readonly class validating and structuring requested inputs)
- **Page Result DTO:** `Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO` (public, final readonly class structuring the returned page result wrapper)
- **Admin Repository:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository` (public final class implementing the Admin Query Interface, injecting only `PDO`)
- **RowMapper:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper` (`/** @internal */ final class` responsible for shared database mapping)
- **DescriptorBuilder:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder` (`/** @internal */ final class` returning query descriptors parameters)
- **Exceptions:**
  - `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException` (public class representing validation failures mapping directly to InvalidArgumentMaatifyException)
  - `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException` (public class representing failure states mapping directly to SystemMaatifyException)

### 2.2 Complete Request DTO Contract

```php
namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;

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

    public static function fromRequest(
        ?string $eventId = null,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $action = null,
        ?string $correlationId = null,
        ?DateTimeImmutable $after = null,
        ?DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null
    ): self {
        $normalizedEventId = self::normalizeString($eventId, 36, 'eventId');
        $normalizedActorType = self::normalizeString($actorType, 32, 'actorType');
        $normalizedTargetType = self::normalizeString($targetType, 64, 'targetType');
        $normalizedAction = self::normalizeString($action, 128, 'action');
        $normalizedCorrelationId = self::normalizeString($correlationId, 36, 'correlationId');

        $normalizedSortBy = self::normalizeSortBy($sortBy);
        $normalizedSortDirection = self::normalizeSortDirection($sortDirection);

        if ($actorId !== null && $actorId <= 0) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('actorId');
        }

        if ($targetId !== null && $targetId <= 0) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('targetId');
        }

        if ($after !== null && $before !== null && $after > $before) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidDateRange();
        }

        return new self(
            eventId: $normalizedEventId,
            actorType: $normalizedActorType,
            actorId: $actorId,
            targetType: $normalizedTargetType,
            targetId: $targetId,
            action: $normalizedAction,
            correlationId: $normalizedCorrelationId,
            after: $after,
            before: $before,
            page: $page,
            perPage: $perPage,
            sortBy: $normalizedSortBy,
            sortDirection: $normalizedSortDirection
        );
    }

    private static function normalizeString(?string $value, int $maxLength, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('/./us', $trimmed)) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        if (strlen($trimmed) > $maxLength) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength($field);
        }

        return $trimmed;
    }

    private static function normalizeSortBy(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === 'occurred_at') {
            return $trimmed;
        }

        return null;
    }

    private static function normalizeSortDirection(?string $value): ?string
    {
        $trimmed = strtoupper(trim((string) $value));
        if ($trimmed === 'ASC' || $trimmed === 'DESC') {
            return $trimmed;
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): mixed
    {
        return [
            'eventId' => $this->eventId,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'action' => $this->action,
            'correlationId' => $this->correlationId,
            'after' => $this->after?->format(DATE_ATOM),
            'before' => $this->before?->format(DATE_ATOM),
            'page' => $this->page,
            'perPage' => $this->perPage,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ];
    }
}
```

**Normalization constraints:**
- **trim**: string inputs are trimmed.
- **empty string**: maps to `null`.
- **UTF-8 validation**: validation occurs natively using `/./us` regular expressions without dependency on `mbstring`.
- **maximums limits**: checked precisely against byte length thresholds.
- **ID validation**: `actorId` and `targetId` must be strictly positive when present.
- **Date boundary validation**: `after` must be <= `before`.
- **Sorting validation**: any short unknown value natively maps to `null` instead of raising a validation error.
- **Pagination mechanics passing**: `page` and `perPage` are directly forwarded to `maatify/persistence` without redefining mechanics.
- Type-only and ID-only search requests are fully permitted.
- `Global Search` is explicitly bypassed (recorded as a future unified cross-domain extension).

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

    /** @return array<string, mixed> */
    public function jsonSerialize(): mixed
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

---

## 3. Storage SQL and Exceptions Contract

### 3.1 Exact Admin SQL

- **totalSql:** `SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log`
- **filteredCountSql:** `SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log {whereSql}`
- **dataSql:** `SELECT id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at FROM maa_event_logging_authoritative_audit_log {whereSql}`
- Filter mappings strictly output precise conditionals mapped to placeholder arrays absent of leading colon indicators:
  - `eventId` → `event_id = :event_id`
  - `actorType` → `actor_type = :actor_type`
  - `actorId` → `actor_id = :actor_id`
  - `targetType` → `target_type = :target_type`
  - `targetId` → `target_id = :target_id`
  - `action` → `action = :action`
  - `correlationId` → `correlation_id = :correlation_id`
  - `after` → `occurred_at >= :after`
  - `before` → `occurred_at <= :before`
- Date objects serialize mapped strings under UTC matching `Y-m-d H:i:s.u`.
- Where clauses cleanly integrate conditionals leveraging exactly equal where structures between data sets.
- Paginator parameters (`ORDER BY`, `LIMIT`, `OFFSET`) are completely abstracted external from SQL payloads parsed by the descriptor.

### 3.2 Pagination Configuration

- **public sort whitelist:** `occurred_at`
- **internal sort whitelist:** `occurred_at`, `id`
- **default sort:** `occurred_at DESC`
- **tie-breaker:** `id DESC` strictly constant.
- **Pagination limits configuration:** default 20, min 1, max 200 via offset pagination configurations.

### 3.3 Primitive Cursor Placeholder Fix

The repeated `:cursor_at` placeholder inside the `find` implementation is corrected behavior-preservingly:
```sql
(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))
```
Both placeholders will receive exactly the same datetime string representation.

### 3.4 Exception Mapping

- **Hierarchy inheritance:** Extending native architecture exceptions, applying interface implementations directly.
- **Exception Factories & Validation Rules:**
  - `Invalid request payload` → `AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId($field)` with `Invalid AuthoritativeAudit Admin Query ID: {field}` or equivalent length, encoding, and range checks mapped.
- **Execution Errors:**
  - `InvalidPaginationConfigurationException` or `InvalidPaginationQueryException` → `AuthoritativeAuditAdminQueryExecutionException::executionFailed($e)` yielding `AuthoritativeAudit Admin Query execution failed: {message}`.
- **Storage/Repository Execution:**
  - `PaginationExecutionException` or `PDOException` → `AuthoritativeAuditStorageException::queryFailed($e)` echoing exact formatting: `Failed to query AuthoritativeAudit records: {message}` while attaching the previous Throwable context.
  - Mapper failures invoking Throwables → `AuthoritativeAuditStorageException::mappingFailed($e)` relaying `Failed to map AuthoritativeAudit row: {message}` holding the exception.
- Prior implementations pushing exact instances of `AuthoritativeAuditStorageException` forward natively bypass double wrapping constraints.

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
- `docs/architecture/ADMIN_QUERY_AUTHORITATIVE_AUDIT_REBUILD_BLUEPRINT.md` (Update status within future Runtime PR)
- `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md` (Update status within future Runtime PR)
- `docs/audits/DOCUMENTATION_INVENTORY.md` (Update status within future Runtime PR)

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
- Proper evaluation of the isolated `eventId` filter.
- Accurate total / filtered / data semantic alignment within pagination routines.
- Exact mapping producing SQL outputs containing precisely generated components.
- Pagination sort normalization mapped cleanly with valid tie-breaker components (id DESC).
- Page limits enforcement targeting valid boundary spans against min/max thresholds.
- Data structures managing accurate output representations mapping precisely to expected extraction variables addressing potential nullable states.
- Custom execution of corrupt JSON validation tests addressing logic cleanly without relying on skipping methods reserved exclusively for rigid SQL integration boundaries.
- Realigned primitive query native PDO placeholders guaranteeing exact functional integrity spanning downstream contexts.
- Guaranteed complete preservation of all protected `v1.0.0` implementations mirroring original constraints.
- Exception structures holding exact message parameters carrying upstream context borders.
- Repository actions passing Storage exceptions without applying duplicate nesting.
- External validation verifying repository borders preventing execution over local transaction elements.
- Verification validating target datasets rely strictly on log structures without intersecting read dependencies onto critical outbox boundaries.
- Full verification of tests executing under live strict MySQL integrations environments natively spanning queries exactly avoiding abstracted persistence mock components.
