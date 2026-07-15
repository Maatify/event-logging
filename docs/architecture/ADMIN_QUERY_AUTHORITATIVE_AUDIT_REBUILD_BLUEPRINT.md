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
  - Limit is normalized as `max(1, $query->limit)`.
  - Ordering remains `occurred_at DESC, id DESC`.
  - Primitive query retains `SELECT * FROM maa_event_logging_authoritative_audit_log`.

- Processing and Hydration:
  - Non-array fetched rows are skipped `if (!is_array($row)) { continue; }`.
  - Explicit hydration fallbacks map:
    - `id`: `(int) $row['id']` fallback to `0`.
    - `event_id`: fallback to empty string `''`.
    - `action`: fallback to empty string `''`.
    - `actor_type`, `actor_id`, `target_type`, `target_id`, `ip_address`, `user_agent`, `correlation_id`: explicit strict types extracted, otherwise mapped to `null`.
    - `changes` JSON extraction loop: missing/non-string/empty/malformed/scalar/numeric-key array -> `null`. Any key non-string -> `null`. Empty associative result -> allowed. Associative object correctly parsed -> `array`.
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
  - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  - `event_id` CHAR(36) NOT NULL
  - `actor_type` VARCHAR(32) NOT NULL
  - `actor_id` BIGINT NULL
  - `action` VARCHAR(128) NOT NULL
  - `target_type` VARCHAR(64) NOT NULL
  - `target_id` BIGINT NULL
  - `risk_level` VARCHAR(16) NOT NULL
  - `payload` JSON NOT NULL
  - `correlation_id` CHAR(36) NOT NULL
  - `created_at` DATETIME(6) NOT NULL
  - UNIQUE KEY `uq_auth_audit_outbox_event_id` (`event_id`)
  - INDEX `idx_auth_audit_outbox_time` (`created_at`, `id`)
  - INDEX `idx_auth_audit_outbox_actor_time` (`actor_type`, `actor_id`, `created_at`)
  - INDEX `idx_auth_audit_outbox_target_time` (`target_type`, `target_id`, `created_at`)
  - INDEX `idx_auth_audit_outbox_correlation_time` (`correlation_id`, `created_at`)
- **maa_event_logging_authoritative_audit_log** (InnoDB):
  - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
  - `event_id` CHAR(36) NOT NULL
  - `actor_type` VARCHAR(32) NOT NULL
  - `actor_id` BIGINT NULL
  - `action` VARCHAR(128) NOT NULL
  - `target_type` VARCHAR(64) NOT NULL
  - `target_id` BIGINT NULL
  - `changes` JSON NULL
  - `ip_address` VARCHAR(45) NULL
  - `user_agent` VARCHAR(512) NULL
  - `correlation_id` CHAR(36) NULL
  - `occurred_at` DATETIME(6) NOT NULL
  - UNIQUE KEY `uq_auth_audit_log_event_id` (`event_id`)
  - INDEX `idx_auth_audit_log_time` (`occurred_at`, `id`)
  - INDEX `idx_auth_audit_log_actor_time` (`actor_type`, `actor_id`, `occurred_at`)
  - INDEX `idx_auth_audit_log_target_time` (`target_type`, `target_id`, `occurred_at`)
  - INDEX `idx_auth_audit_log_correlation_time` (`correlation_id`, `occurred_at`)
  - INDEX `idx_auth_audit_log_action_time` (`action`, `occurred_at`)
- No schema change is authorized.
- The `maa_event_logging_authoritative_audit_outbox` table is the absolute transactional fail-closed source of truth for writes.
- The Admin query and primitive read operations will exclusively target the materialized log `maa_event_logging_authoritative_audit_log`.

---

## 2. Target Admin Query API

### 2.1 API Components

The Admin Query API introduces the following new classes within the package, properly isolated from the primitive API:
- **Admin Interface:** `Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditAdminQueryInterface`
- **Request DTO:** `Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO`
- **Page Result DTO:** `Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO`
- **Admin Repository:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository` (`public final` infrastructure adapter)
- **RowMapper:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper` (`/** @internal */ final`)
- **DescriptorBuilder:** `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder` (`/** @internal */ final`)
- **Exceptions:**
  - `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException`
  - `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException`

### 2.2 Complete Public Interface Contract

```php
namespace Maatify\EventLogging\AuthoritativeAudit\Contract;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;

interface AuthoritativeAuditAdminQueryInterface
{
    /**
     * @throws AuthoritativeAuditStorageException
     * @throws AuthoritativeAuditAdminQueryExecutionException
     * @throws AuthoritativeAuditAdminQueryInvalidArgumentException
     */
    public function paginate(AuthoritativeAuditAdminQueryRequestDTO $request): AuthoritativeAuditAdminPageResultDTO;
}
```

### 2.3 Complete Request DTO Contract

```php
namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;

final readonly class AuthoritativeAuditAdminQueryRequestDTO implements JsonSerializable
{
    public ?string $eventId;
    public ?string $actorType;
    public ?int $actorId;
    public ?string $targetType;
    public ?int $targetId;
    public ?string $action;
    public ?string $correlationId;
    public ?DateTimeImmutable $after;
    public ?DateTimeImmutable $before;
    public int|string|null $page;
    public int|string|null $perPage;
    public ?string $sortBy;
    public ?string $sortDirection;

    public function __construct(
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
    ) {
        $this->eventId = self::normalizeString($eventId, 36, 'eventId');
        $this->actorType = self::normalizeString($actorType, 32, 'actorType');
        $this->targetType = self::normalizeString($targetType, 64, 'targetType');
        $this->action = self::normalizeString($action, 128, 'action');
        $this->correlationId = self::normalizeString($correlationId, 36, 'correlationId');

        $this->sortBy = self::normalizeSortBy($sortBy);
        $this->sortDirection = self::normalizeSortDirection($sortDirection);

        if ($actorId !== null && $actorId <= 0) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('actorId');
        }
        $this->actorId = $actorId;

        if ($targetId !== null && $targetId <= 0) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('targetId');
        }
        $this->targetId = $targetId;

        $afterUtc = $after?->setTimezone(new DateTimeZone('UTC'));
        $beforeUtc = $before?->setTimezone(new DateTimeZone('UTC'));

        if ($afterUtc !== null && $beforeUtc !== null && $afterUtc > $beforeUtc) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidDateRange();
        }
        $this->after = $afterUtc;
        $this->before = $beforeUtc;

        $this->page = $page;
        $this->perPage = $perPage;
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

        $length = preg_match_all('/./us', $trimmed);
        if ($length === false || $length === 0) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        if ($length > $maxLength) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength($field);
        }

        return $trimmed;
    }

    private static function normalizeSortBy(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $length = preg_match_all('/./us', $trimmed);
        if ($length === false || $length === 0) {
            return null;
        }

        if ($length > 64) {
            return null;
        }

        if ($trimmed === 'occurred_at') {
            return $trimmed;
        }

        return null;
    }

    private static function normalizeSortDirection(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = strtoupper(trim($value));
        if ($trimmed === '') {
            return null;
        }

        $length = preg_match_all('/./us', $trimmed);
        if ($length === false || $length === 0) {
            return null;
        }

        if ($length > 4) {
            return null;
        }

        if ($trimmed === 'ASC' || $trimmed === 'DESC') {
            return $trimmed;
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
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

### 2.4 Complete Page Result DTO Contract

```php
namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

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

    /** @return ArrayIterator<int, AuthoritativeAuditViewDTO> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return array<string, mixed> */
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

### 2.5 Exact Repository and Mapper Contracts

**Repository Contract (`public final` infrastructure adapter):**
```php
namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql;

use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditAdminQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder;
use Maatify\Persistence\Pagination\Contract\PaginationExceptionInterface;
use Maatify\Persistence\Pagination\DTO\PageRequest;
use Maatify\Persistence\Pagination\Enum\SortDirectionEnum;
use Maatify\Persistence\Pagination\PdoPaginator;
use Maatify\Persistence\Pagination\ValueObject\PaginationConfig;
use Maatify\Persistence\Pagination\ValueObject\SortWhitelist;
use PDO;
use PDOException;
use Throwable;

final class AuthoritativeAuditAdminQueryMysqlRepository implements AuthoritativeAuditAdminQueryInterface
{
    private AuthoritativeAuditRowMapper $mapper;
    private AuthoritativeAuditAdminQueryDescriptorBuilder $descriptorBuilder;
    private PdoPaginator $paginator;

    public function __construct(private PDO $pdo)
    {
        $this->mapper = new AuthoritativeAuditRowMapper();
        $this->descriptorBuilder = new AuthoritativeAuditAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator($this->pdo, $this->createPaginationConfig());
    }

    public function paginate(AuthoritativeAuditAdminQueryRequestDTO $request): AuthoritativeAuditAdminPageResultDTO
    {
        $descriptor = $this->descriptorBuilder->build($request);

        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection
        );

        try {
            $result = $this->paginator->paginate($descriptor, $pageRequest);
        } catch (PaginationExceptionInterface $e) {
            throw AuthoritativeAuditAdminQueryExecutionException::executionFailed($e);
        } catch (PDOException $e) {
            throw new AuthoritativeAuditStorageException('Failed to query AuthoritativeAudit records: ' . $e->getMessage(), 0, $e);
        }

        $items = [];
        foreach ($result->data as $row) {
            if (! is_array($row)) {
                continue;
            }
            /** @var array<string, mixed> $row */
            $items[] = $this->mapRow($row);
        }

        return new AuthoritativeAuditAdminPageResultDTO(
            items: $items,
            page: $result->page,
            perPage: $result->perPage,
            total: $result->total,
            filtered: $result->filtered,
            totalPages: $result->totalPages,
            hasNext: $result->hasNext,
            hasPrevious: $result->hasPrevious,
            sortBy: $result->sortBy,
            sortDirection: $result->sortDirection->value
        );
    }

    private function createPaginationConfig(): PaginationConfig
    {
        return new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'occurred_at' => 'occurred_at',
                'id' => 'id',
            ]),
            defaultSortBy: 'occurred_at',
            defaultSortDirection: SortDirectionEnum::DESC,
            tieBreakerSortBy: 'id',
            tieBreakerDirection: SortDirectionEnum::DESC,
            defaultPerPage: 20,
            minPerPage: 1,
            maxPerPage: 200,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @throws AuthoritativeAuditStorageException
     */
    private function mapRow(array $row): AuthoritativeAuditViewDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (AuthoritativeAuditStorageException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new AuthoritativeAuditStorageException('Failed to map AuthoritativeAudit row: ' . $e->getMessage(), 0, $e);
        }
    }
}
```

**RowMapper Contract (`/** @internal */ final`):**
```php
namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditViewDTO;

/** @internal */
final class AuthoritativeAuditRowMapper
{
    /**
     * @param array<string, mixed> $row
     * @throws Exception
     */
    public function map(array $row): AuthoritativeAuditViewDTO
    {
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $eventId = is_string($row['event_id'] ?? null) ? $row['event_id'] : '';
        $actorType = is_string($row['actor_type'] ?? null) ? $row['actor_type'] : null;
        $actorId = isset($row['actor_id']) && is_numeric($row['actor_id']) ? (int) $row['actor_id'] : null;
        $action = is_string($row['action'] ?? null) ? $row['action'] : '';
        $targetType = is_string($row['target_type'] ?? null) ? $row['target_type'] : null;
        $targetId = isset($row['target_id']) && is_numeric($row['target_id']) ? (int) $row['target_id'] : null;
        $ipAddress = is_string($row['ip_address'] ?? null) ? $row['ip_address'] : null;
        $userAgent = is_string($row['user_agent'] ?? null) ? $row['user_agent'] : null;
        $correlationId = is_string($row['correlation_id'] ?? null) ? $row['correlation_id'] : null;

        $changes = null;
        if (isset($row['changes']) && is_string($row['changes']) && $row['changes'] !== '') {
            try {
                $decoded = json_decode($row['changes'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $isAssociative = false;
                    foreach (array_keys($decoded) as $key) {
                        if (!is_string($key)) {
                            $isAssociative = false;
                            break;
                        }
                        $isAssociative = true;
                    }
                    if ($isAssociative || empty($decoded)) {
                        $changes = $decoded;
                    }
                }
            } catch (JsonException $e) {
                $changes = null;
            }
        }

        $occurredAtString = is_string($row['occurred_at'] ?? null) ? $row['occurred_at'] : '1970-01-01 00:00:00';
        $occurredAt = new DateTimeImmutable($occurredAtString, new DateTimeZone('UTC'));

        return new AuthoritativeAuditViewDTO(
            $id,
            $eventId,
            $actorType,
            $actorId,
            $action,
            $targetType,
            $targetId,
            $ipAddress,
            $userAgent,
            $correlationId,
            $changes,
            $occurredAt
        );
    }
}
```

---

## 3. Storage SQL and Exceptions Contract

### 3.1 Exact Admin SQL DescriptorBuilder

**DescriptorBuilder Contract (`/** @internal */ final`):**
```php
namespace Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination;

use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\Persistence\Pagination\DTO\PdoPaginationQueryDescriptor;

/** @internal */
final class AuthoritativeAuditAdminQueryDescriptorBuilder
{
    public function build(AuthoritativeAuditAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        $filtered = $this->buildFilteredWhereAndParams($request);
        $whereSql = $filtered['whereSql'];
        $params = $filtered['params'];

        $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log';
        $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_authoritative_audit_log' . $whereSql;
        $dataSql = 'SELECT id, event_id, actor_type, actor_id, action, target_type, target_id, ip_address, user_agent, correlation_id, changes, occurred_at FROM maa_event_logging_authoritative_audit_log' . $whereSql;

        return new PdoPaginationQueryDescriptor(
            totalSql: $totalSql,
            totalParams: [],
            filteredCountSql: $filteredCountSql,
            filteredCountParams: $params,
            dataSql: $dataSql,
            dataParams: $params
        );
    }

    /** @return array{whereSql: string, params: array<string, string|int|bool|null>} */
    private function buildFilteredWhereAndParams(AuthoritativeAuditAdminQueryRequestDTO $request): array
    {
        $conditions = [];
        $params = [];

        if ($request->eventId !== null) {
            $conditions[] = 'event_id = :event_id';
            $params['event_id'] = $request->eventId;
        }
        if ($request->actorType !== null) {
            $conditions[] = 'actor_type = :actor_type';
            $params['actor_type'] = $request->actorType;
        }
        if ($request->actorId !== null) {
            $conditions[] = 'actor_id = :actor_id';
            $params['actor_id'] = $request->actorId;
        }
        if ($request->targetType !== null) {
            $conditions[] = 'target_type = :target_type';
            $params['target_type'] = $request->targetType;
        }
        if ($request->targetId !== null) {
            $conditions[] = 'target_id = :target_id';
            $params['target_id'] = $request->targetId;
        }
        if ($request->action !== null) {
            $conditions[] = 'action = :action';
            $params['action'] = $request->action;
        }
        if ($request->correlationId !== null) {
            $conditions[] = 'correlation_id = :correlation_id';
            $params['correlation_id'] = $request->correlationId;
        }
        if ($request->after !== null) {
            $conditions[] = 'occurred_at >= :after';
            $params['after'] = $request->after->format('Y-m-d H:i:s.u');
        }
        if ($request->before !== null) {
            $conditions[] = 'occurred_at <= :before';
            $params['before'] = $request->before->format('Y-m-d H:i:s.u');
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [
            'whereSql' => $whereSql,
            'params' => $params
        ];
    }
}
```

### 3.2 Primitive Cursor Placeholder Fix

The repeated `:cursor_at` placeholder inside the primitive `find` implementation is corrected behavior-preservingly:
```sql
(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))
```
Both placeholders will receive exactly the same datetime string representation.

### 3.3 Exception Classes and Mappings

**Hierarchy:**
Both exceptions implement `Maatify\EventLogging\Exception\EventLoggingExceptionInterface`.
- `AuthoritativeAuditAdminQueryInvalidArgumentException` extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException`.
- `AuthoritativeAuditAdminQueryExecutionException` extends `Maatify\Exceptions\Exception\System\SystemMaatifyException`.

**Factories:**
- `AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId(string $field): self`
  - Message: `Invalid AuthoritativeAudit Admin Query ID: {field}`
- `AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength(string $field): self`
  - Message: `Invalid AuthoritativeAudit Admin Query length: {field}`
- `AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding(string $field): self`
  - Message: `Invalid AuthoritativeAudit Admin Query UTF-8 encoding: {field}`
- `AuthoritativeAuditAdminQueryInvalidArgumentException::invalidDateRange(): self`
  - Message: `Invalid AuthoritativeAudit Admin Query date range: after must be before or equal to before`
- `AuthoritativeAuditAdminQueryExecutionException::executionFailed(\Throwable $previous): self`
  - Message: `AuthoritativeAudit Admin Query execution failed: {previous message}`

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

### 4.2 Modified:
- `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditQueryMysqlRepository.php`
- `docs/integration/ADMIN_READ_USAGE.md`
- `src/AuthoritativeAudit/README.md`
- `EVENT_LOGGING_PACKAGE_REFERENCE.md`
- `CHANGELOG.md`
- `tests/Integration/AuthoritativeAudit/AuthoritativeAuditRepositoryTest.php` (This file remains exactly in its path. It will be amended to assert primitive cursor fixes and storage semantics, continuing to serve as the unified Outbox/Primitive Integration proof. No nested integration testing namespace creation is authorized).
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
- Exact SQL descriptor outputs mapping variables to fields using UTC configurations.
- Pagination sort normalization mapped cleanly with valid tie-breaker components (id DESC).
- Page limits enforcement targeting valid boundary spans against min/max thresholds.
- Safe nullable fields extraction mimicking internal JSON properties accurately.
- Dedicated Unit and Regression tests handling exact corrupt JSON mapper processing logic without relying on skip mechanics in rigid schema instances.
- Realigned primitive query native PDO placeholders guaranteeing exact functional integrity spanning downstream contexts.
- Guaranteed complete preservation of all protected `v1.0.0` implementations mirroring original constraints.
- Exception structures holding exact message parameters carrying upstream context borders.
- Repository actions passing Storage exceptions without applying duplicate nesting.
- External validation verifying repository borders preventing execution over local transaction elements.
- Verification validating target datasets rely strictly on log structures without intersecting read dependencies onto critical outbox boundaries.
- Full verification of tests executing under live strict MySQL integrations environments spanning queries without abstracted persistence mock components.
