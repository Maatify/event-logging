# Owner Approved / Runtime Implementation Authorized

## 1. Audit the Current Main State

* **Exact audited main SHA:** `59ac1afee2313172d11de4f008169c5fd9c824a6`
* **Exact maatify/persistence Composer constraint currently installed:** `^1.1.0`
* **Current package exception marker state:** `Maatify\EventLogging\Exception\EventLoggingExceptionInterface` (extends `\Throwable`). `BehaviorTraceStorageException` extends `SystemMaatifyException`, implements the package marker, and retains `ErrorCodeEnum::DATABASE_CONNECTION_FAILED`.
* **AuditTrail POC merge commit:** `59ac1afee2313172d11de4f008169c5fd9c824a6` (PR #98)
* **Exact current BehaviorTrace Runtime and test inventory:**
  * `src/BehaviorTrace/Contract/BehaviorTraceQueryInterface.php` (contains `find` and `read` method signatures)
  * `src/BehaviorTrace/DTO/BehaviorTraceQueryDTO.php`
  * `src/BehaviorTrace/DTO/BehaviorTraceCursorDTO.php`
  * `src/BehaviorTrace/DTO/BehaviorTraceEventDTO.php`
  * `src/BehaviorTrace/DTO/BehaviorTraceContextDTO.php`
  * `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceQueryMysqlRepository.php` (constructor: `public function __construct(private readonly PDO $pdo, ?BehaviorTracePolicyInterface $policy = null)`)
  * `src/BehaviorTrace/Contract/BehaviorTracePolicyInterface.php`
  * `src/BehaviorTrace/Recorder/BehaviorTraceDefaultPolicy.php`
  * `src/BehaviorTrace/Exception/BehaviorTraceStorageException.php`
  * `tests/Unit/BehaviorTrace/DTO/BehaviorTraceQueryDTOTest.php`
  * `tests/Unit/BehaviorTrace/DTO/BehaviorTraceContextDTOTest.php`
  * `tests/Unit/BehaviorTrace/DTO/BehaviorTraceEventDTOTest.php`
  * `tests/Unit/BehaviorTrace/DTO/BehaviorTraceCursorDTOTest.php`
  * `tests/Unit/BehaviorTrace/Repository/BehaviorTraceQueryMysqlRepositoryTest.php`
  * `tests/Integration/BehaviorTrace/BehaviorTraceRepositoryTest.php`

## 2. Protected Primitive Behavior

The following primitive BehaviorTrace contracts must be perfectly preserved by the future Runtime PR:

* `src/BehaviorTrace/Contract/BehaviorTraceQueryInterface.php`
* `src/BehaviorTrace/DTO/BehaviorTraceQueryDTO.php`
* `src/BehaviorTrace/DTO/BehaviorTraceCursorDTO.php`
* `src/BehaviorTrace/DTO/BehaviorTraceEventDTO.php`
* `src/BehaviorTrace/DTO/BehaviorTraceContextDTO.php`
* `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceQueryMysqlRepository.php`
* `src/BehaviorTrace/Contract/BehaviorTracePolicyInterface.php`
* `src/BehaviorTrace/Recorder/BehaviorTraceDefaultPolicy.php`
* `src/BehaviorTrace/Exception/BehaviorTraceStorageException.php`

### `find()`

Exact signature:
```php
public function find(BehaviorTraceQueryDTO $query): array;
```

* filtered primitive query;
* descending order: `occurred_at DESC, id DESC`;
* primitive cursor fields: `cursorOccurredAt`, `cursorId`;
* current limit normalization;
* current storage and mapper exception messages.

### `read()`

Exact signature:
```php
public function read(
    ?BehaviorTraceCursorDTO $cursor,
    int $limit = 100,
): iterable;
```

* forward sequential stream;
* ascending order: `occurred_at ASC, id ASC`;
* uses `BehaviorTraceCursorDTO`;
* generator/iterable behavior;
* current limit binding;
* current storage and mapper exception messages.

The future Admin Query API must not replace, merge, redesign, or remove either method.

## 3. Preserve the Existing Repository Constructor and Policy Semantics

The primitive repository constructor is protected:

```php
public function __construct(
    PDO $pdo,
    ?BehaviorTracePolicyInterface $policy = null
)
```

The blueprint must explicitly preserve:

* constructor parameter names;
* parameter order;
* nullable custom policy support;
* fallback to `BehaviorTraceDefaultPolicy`;
* actor-type normalization through the effective policy;
* custom host policy behavior;
* row hydration fallbacks;
* metadata JSON decoding behavior;
* existing `find()` and `read()` exception boundaries.

### Complete the Shared Mapper and Primitive Refactor Contract

Define the exact `BehaviorTraceRowMapper` contract:

```php
/** @internal */
final class BehaviorTraceRowMapper
{
    public function __construct(
        private BehaviorTracePolicyInterface $policy,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): BehaviorTraceEventDTO
    {
        // implementation follows exactly the previous mapping logic
    }
}
```

Define the primitive repository refactor exactly:

```php
private BehaviorTraceRowMapper $mapper;

public function __construct(
    private readonly PDO $pdo,
    ?BehaviorTracePolicyInterface $policy = null,
) {
    $this->mapper = new BehaviorTraceRowMapper(
        $policy ?? new BehaviorTraceDefaultPolicy(),
    );
}
```

The future implementation must:
* remove the primitive repository’s private duplicated `mapRowToDTO()` method;
* call `$this->mapper->map($row)` from both `find()` and `read()`;
* preserve the constructor name, parameter order, defaults, and visibility;
* preserve the exact existing `find()` and `read()` catch boundaries and messages;
* preserve custom policy behavior;
* make no change to the two public primitive method signatures.

## 4. Inventory the Superseded Post-v1 Artifacts

The following current artifacts are explicitly classified as: **Superseded Post-v1 Experiment**

* `src/BehaviorTrace/Contract/BehaviorTracePaginatedQueryInterface.php`
* `src/BehaviorTrace/DTO/BehaviorTraceQueryCursorDTO.php`
* `src/BehaviorTrace/DTO/BehaviorTraceQueryPageDTO.php`
* `src/BehaviorTrace/Service/BehaviorTracePaginatedQueryService.php`

Related tests:
* `tests/Unit/BehaviorTrace/Service/BehaviorTracePaginatedQueryServiceTest.php`
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceQueryCursorDTOTest.php`
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceQueryPageDTOTest.php`

Documentation references to the superseded post-v1 BehaviorTrace pagination artifacts or their wildcard artifact family:
* `EVENT_LOGGING_PACKAGE_REFERENCE.md` (Contains wildcard artifact-family and architecture/history references; it does not list the four BehaviorTrace superseded classes by exact FQCN.)
* `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md` (Contains wildcard and roadmap/status references)
* `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md` (Contains roadmap/status references to the superseded post-v1 experiment)
* `docs/audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md` (Contains exact references and roadmap/status references)
* `docs/audits/DOCUMENTATION_INVENTORY.md` (Contains roadmap/status references)

These documentation references are updated or retained as architecture/history references; they are not Runtime consumers blocking future deletion.

These are not protected `v1.0.0` primitive contracts.
They must not be deleted until the replacement Runtime passes its complete compatibility gate.

## 5. Define the Separate Public Admin Query API

The future public interface:

```php
namespace Maatify\EventLogging\BehaviorTrace\Contract;

use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminPageResultDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryExecutionException;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryInvalidArgumentException;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;

interface BehaviorTraceAdminQueryInterface
{
    /**
     * @throws BehaviorTraceAdminQueryInvalidArgumentException
     * @throws BehaviorTraceAdminQueryExecutionException
     * @throws BehaviorTraceStorageException
     */
    public function paginate(
        BehaviorTraceAdminQueryRequestDTO $request
    ): BehaviorTraceAdminPageResultDTO;
}
```

The public API must not expose any `maatify/persistence` class.

### Exact Request DTO Contract

Define the exact `final readonly` request DTO: `BehaviorTraceAdminQueryRequestDTO`

```php
final readonly class BehaviorTraceAdminQueryRequestDTO implements \JsonSerializable
{
    public ?string $actorType;
    public ?int $actorId;
    public ?string $action;
    public ?string $entityType;
    public ?int $entityId;
    public ?string $requestId;
    public ?string $correlationId;
    public ?\DateTimeImmutable $after;
    public ?\DateTimeImmutable $before;
    public int|string|null $page;
    public int|string|null $perPage;
    public ?string $sortBy;
    public ?string $sortDirection;

    public function __construct(
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $action = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?\DateTimeImmutable $after = null,
        ?\DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
    ) {
        $this->actorType = self::normalizeNullableString($actorType, 'actorType', 32);
        $this->actorId = self::validatePositiveNullableId($actorId, 'actorId');

        $this->action = self::normalizeNullableString($action, 'action', 128);

        $this->entityType = self::normalizeNullableString($entityType, 'entityType', 64);
        $this->entityId = self::validatePositiveNullableId($entityId, 'entityId');

        $this->requestId = self::normalizeNullableString($requestId, 'requestId', 64);
        $this->correlationId = self::normalizeNullableString($correlationId, 'correlationId', 36);

        if ($after !== null && $before !== null && $after > $before) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidDateRange();
        }
        $this->after = $after;
        $this->before = $before;

        $this->page = $page;
        $this->perPage = $perPage;

        $normalizedSortBy = self::normalizeNullableString(
            $sortBy,
            'sortBy',
            64,
        );

        $this->sortBy = $normalizedSortBy === 'occurred_at'
            ? 'occurred_at'
            : null;

        $normalizedSortDirection = self::normalizeNullableString(
            $sortDirection,
            'sortDirection',
            4,
        );

        $this->sortDirection = $normalizedSortDirection !== null
            && in_array(strtoupper($normalizedSortDirection), ['ASC', 'DESC'], true)
                ? strtoupper($normalizedSortDirection)
                : null;

        if ($this->actorId !== null && $this->actorType === null) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidId('actorId without actorType');
        }

        if ($this->entityId !== null && $this->entityType === null) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidId('entityId without entityType');
        }
    }

    private static function utf8Length(string $value, string $field): int
    {
        $length = preg_match_all('/./us', $value);

        if ($length === false) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        return $length;
    }

    private static function normalizeNullableString(
        ?string $value,
        string $field,
        int $maxLength,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (self::utf8Length($trimmed, $field) > $maxLength) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidLength($field);
        }

        return $trimmed;
    }

    private static function validatePositiveNullableId(
        ?int $value,
        string $field,
    ): ?int {
        if ($value === null) {
            return null;
        }

        if ($value <= 0) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidId($field);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'action' => $this->action,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'requestId' => $this->requestId,
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

Required behavior:

```text
sortBy = occurred_at -> occurred_at
sortBy = id          -> null
other short sortBy   -> null
overlong sortBy      -> invalidLength

sortDirection = asc  -> ASC
sortDirection = desc -> DESC
short invalid value  -> null
overlong value       -> invalidLength
invalid UTF-8        -> invalidEncoding(field)
```

Validation rules match the exact max schema lengths and pair requirements defined above.
UTF-8 validation must not require `ext-mbstring` (it uses `preg_match_all` with `/us`). DTO JSON dates use `DATE_ATOM`.

### Exact Result DTO Contract

Provide the full contract:

```php
/**
 * @implements \IteratorAggregate<int, BehaviorTraceEventDTO>
 */
final readonly class BehaviorTraceAdminPageResultDTO implements \IteratorAggregate, \JsonSerializable
{
    /**
     * @param list<BehaviorTraceEventDTO> $items
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
        public string $sortDirection,
    ) {
    }

    /**
     * @return \ArrayIterator<int, BehaviorTraceEventDTO>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return array<string, mixed>
     */
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

JSON key must be `items`.

## 6. Define Pagination and SQL Architecture

The future implementation must use the already-installed `maatify/persistence ^1.1.0`.

Expected components:
* `BehaviorTraceAdminQueryMysqlRepository`
* `Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\Pagination\BehaviorTraceAdminQueryDescriptorBuilder` (Path: `src/BehaviorTrace/Infrastructure/Mysql/Pagination/BehaviorTraceAdminQueryDescriptorBuilder.php`)
* `BehaviorTraceRowMapper`

Exact table: `maa_event_logging_behavior_trace`

The blueprint defines:

* Total SQL:
  ```sql
  SELECT COUNT(*) FROM maa_event_logging_behavior_trace
  ```

* Data columns:
  ```text
  id, event_id, actor_type, actor_id, action, entity_type, entity_id,
  metadata, correlation_id, request_id, route_name, ip_address,
  user_agent, occurred_at
  ```

* Complete descriptor construction and shared condition/parameter method:
  ```php
  /** @internal */
  final class BehaviorTraceAdminQueryDescriptorBuilder
  {
      public function build(
          BehaviorTraceAdminQueryRequestDTO $request
      ): PdoPaginationQueryDescriptor {
          $filter = $this->buildFilteredWhereAndParams($request);
          $whereClause = $filter['whereSql'];
          $params = $filter['params'];

          $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_behavior_trace';

          $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_behavior_trace' . $whereClause;

          $dataSql = 'SELECT id, event_id, actor_type, actor_id, action, entity_type, entity_id, '
                   . 'metadata, correlation_id, request_id, route_name, ip_address, '
                   . 'user_agent, occurred_at '
                   . 'FROM maa_event_logging_behavior_trace' . $whereClause;

          return new PdoPaginationQueryDescriptor(
              totalSql: $totalSql,
              totalParams: [],
              filteredCountSql: $filteredCountSql,
              filteredCountParams: $params,
              dataSql: $dataSql,
              dataParams: $params,
          );
      }

      /**
       * @return array{
       *     whereSql: string,
       *     params: array<string, string|int|bool|null>
       * }
       */
      private function buildFilteredWhereAndParams(
          BehaviorTraceAdminQueryRequestDTO $request
      ): array {
          $where = [];
          $params = [];

          if ($request->actorType !== null) {
              $where[] = 'actor_type = :actor_type';
              $params['actor_type'] = $request->actorType;
          }

          if ($request->actorId !== null) {
              $where[] = 'actor_id = :actor_id';
              $params['actor_id'] = $request->actorId;
          }

          if ($request->action !== null) {
              $where[] = 'action = :action';
              $params['action'] = $request->action;
          }

          if ($request->entityType !== null) {
              $where[] = 'entity_type = :entity_type';
              $params['entity_type'] = $request->entityType;
          }

          if ($request->entityId !== null) {
              $where[] = 'entity_id = :entity_id';
              $params['entity_id'] = $request->entityId;
          }

          if ($request->requestId !== null) {
              $where[] = 'request_id = :request_id';
              $params['request_id'] = $request->requestId;
          }

          if ($request->correlationId !== null) {
              $where[] = 'correlation_id = :correlation_id';
              $params['correlation_id'] = $request->correlationId;
          }

          $utc = new \DateTimeZone('UTC');
          if ($request->after !== null) {
              $where[] = 'occurred_at >= :after';
              $params['after'] = $request->after->setTimezone($utc)->format('Y-m-d H:i:s.u');
          }

          if ($request->before !== null) {
              $where[] = 'occurred_at <= :before';
              $params['before'] = $request->before->setTimezone($utc)->format('Y-m-d H:i:s.u');
          }

          $whereClause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

          return [
              'whereSql' => $whereClause,
              'params' => $params,
          ];
      }
  }
  ```

* explicit selected-column list;
* no `SELECT *` in Admin Query SQL;
* no `ORDER BY`, `LIMIT`, or `OFFSET` inside the descriptor data SQL;
* Dates must be converted to UTC before database formatting.
* named parameters without leading colons in descriptor arrays;
* no reserved `__pagination_` parameter prefix.

Filter mapping:
* actorType      -> actor_type
* actorId        -> actor_id
* action         -> action
* entityType     -> entity_type
* entityId       -> entity_id
* requestId      -> request_id
* correlationId  -> correlation_id
* after          -> occurred_at >=
* before         -> occurred_at <=

Canonical pagination configuration:
* default sort: occurred_at DESC
* tie-breaker: id DESC
* default per page: 20
* minimum per page: 1
* maximum per page: 200

### Define Exact Admin Repository Execution Contract

Replace the current incomplete Admin Repository section with a complete executable contract.

Exact target:

```php
final class BehaviorTraceAdminQueryMysqlRepository implements BehaviorTraceAdminQueryInterface
{
    private BehaviorTraceRowMapper $mapper;

    private BehaviorTraceAdminQueryDescriptorBuilder $descriptorBuilder;

    private PdoPaginator $paginator;

    public function __construct(
        private PDO $pdo,
        ?BehaviorTracePolicyInterface $policy = null,
    ) {
        $effectivePolicy = $policy ?? new BehaviorTraceDefaultPolicy();

        $this->mapper = new BehaviorTraceRowMapper($effectivePolicy);
        $this->descriptorBuilder = new BehaviorTraceAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();
    }

    public function paginate(
        BehaviorTraceAdminQueryRequestDTO $request,
    ): BehaviorTraceAdminPageResultDTO {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection,
        );

        try {
            $descriptor = $this->descriptorBuilder->build($request);
            $paginationConfig = $this->createPaginationConfig();

            $result = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $paginationConfig,
                fn (array $row): BehaviorTraceEventDTO => $this->mapRow($row),
            );
        } catch (PaginationExecutionException | PDOException $exception) {
            throw new BehaviorTraceStorageException(
                message: 'Failed to query BehaviorTrace records: '
                    . $exception->getMessage(),
                previous: $exception,
            );
        } catch (
            InvalidPaginationConfigurationException
            | InvalidPaginationQueryException $exception
        ) {
            throw BehaviorTraceAdminQueryExecutionException::executionFailed(
                $exception,
            );
        }

        return new BehaviorTraceAdminPageResultDTO(
            items: $result->data,
            page: $result->page,
            perPage: $result->perPage,
            total: $result->total,
            filtered: $result->filtered,
            totalPages: $result->totalPages,
            hasNext: $result->hasNext,
            hasPrevious: $result->hasPrevious,
            sortBy: $result->sortBy,
            sortDirection: $result->sortDirection->value,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): BehaviorTraceEventDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (\Exception $exception) {
            throw new BehaviorTraceStorageException(
                message: 'Failed to map BehaviorTrace row: '
                    . $exception->getMessage(),
                previous: $exception,
            );
        }
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
}
```

State explicitly:
* `BehaviorTraceStorageException` thrown by `mapRow()` propagates unchanged through `paginate()`.
* It must not be rewrapped as a paginator execution failure.
* The repository owns no transaction.
* No injectable paginator or production test seam is authorized.

## 7. Define Policy-Aware Row Hydration

Document every current fallback exactly:
* `actor_type`: non-string -> ANONYMOUS, then effective policy normalization
* `actor_id`: numeric -> int, otherwise null
* `id`: numeric -> int, otherwise 0
* `event_id`: string, otherwise empty string
* `action`: string, otherwise unknown
* `entity_type`: string, otherwise null
* `entity_id`: numeric -> int, otherwise null
* `correlation_id/request_id/route_name/ip_address/user_agent`: string, otherwise null
* `metadata`: valid JSON array -> array, otherwise null
* `occurred_at`: non-string -> 1970-01-01 00:00:00 UTC
* invalid DateTime string or policy exception: mapper throws Exception

**Decision for mapper/policy failures:** The primitive repository catches `\Exception` around the mapping process and translates it to `BehaviorTraceStorageException`. The future shared mapper must throw its raw `\Exception` on failure. The `BehaviorTraceAdminQueryMysqlRepository` must catch this exception through the dedicated `mapRow` method and translate it to `BehaviorTraceStorageException`, explicitly preserving the original exception as the previous throwable, matching exactly the primitive boundary's translation strategy.

Preserve the primitive exception messages exactly:
* find PDO: Failed to query BehaviorTrace records: {message}
* find mapper: Failed to map BehaviorTrace row: {message}
* read PDO: Failed to read behavior trace: {message}
* read mapper: Failed to map behavior trace row: {message}

## 8. Define Exception Architecture

Future exceptions:
* `BehaviorTraceAdminQueryInvalidArgumentException`
* `BehaviorTraceAdminQueryExecutionException`

Requirements:
* both implement `EventLoggingExceptionInterface`;
* previous throwables are preserved;
* no generic `RuntimeException`;
* no generic `Throwable` catch unless current BehaviorTrace policy/hydration behavior proves it is required and the blueprint documents the exact reason.

Validation exception (`BehaviorTraceAdminQueryInvalidArgumentException`):
* extends `InvalidArgumentMaatifyException`
* implements `EventLoggingExceptionInterface`
* `ErrorCodeEnum::INVALID_ARGUMENT`

Named constructors and exact messages:
* `invalidId($field)`: `Invalid BehaviorTrace Admin Query ID: {field}`
* `invalidLength($field)`: `Invalid BehaviorTrace Admin Query length: {field}`
* `invalidEncoding($field)`: `Invalid BehaviorTrace Admin Query UTF-8 encoding: {field}`
* `invalidDateRange()`: `Invalid BehaviorTrace Admin Query date range: after must be before or equal to before`

Execution exception (`BehaviorTraceAdminQueryExecutionException`):
* extends `SystemMaatifyException`
* implements `EventLoggingExceptionInterface`
* `ErrorCodeEnum::MAATIFY_ERROR`

Named constructor:
```php
public static function executionFailed(\Throwable $previous): self
```
Stable message: `BehaviorTrace Admin Query execution failed: {previous message}`

Define exact Admin repository translations:
* `PDOException | PaginationExecutionException`
  -> `BehaviorTraceStorageException`
  -> `Failed to query BehaviorTrace records: {message}`
* `InvalidPaginationConfigurationException | InvalidPaginationQueryException`
  -> `BehaviorTraceAdminQueryExecutionException`
* `mapper/policy Exception`
  -> `BehaviorTraceStorageException`
  -> `Failed to map BehaviorTrace row: {message}`

Always preserve `previous`.

## 9. Primitive Cursor Compatibility Hazard

**Verdict:** Behavior-Preserving Primitive Compatibility Correction — Pending Owner Approval

Currently, `find()` uses the `cursor_at` placeholder in two places within the `OR` clause. Native prepared statements in MySQL strictly require unique placeholders when reusing values, meaning separate placeholders such as `cursor_at_before` and `cursor_at_equal` are required.

*   **Current behavior:** The package does not configure prepare emulation.
*   **MySQL native-prepared-statement impact:** Reusing `cursor_at` causes PDO to throw an exception.
*   **Semantic impact:** There is absolutely no change to the query semantics or logic.
*   **Backward-compatibility impact:** None. The API surface, query results, and external behavior remain exactly identical.
*   **Required regression and integration evidence:** Real MySQL integration tests for `find()` must verify the correct descending cursor output using native prepared statements to prove that splitting the placeholder preserves the identical results.

Approve only this behavior-preserving future correction:

```sql
(occurred_at < :cursor_at_before
 OR (occurred_at = :cursor_at_equal AND id < :cursor_id))
```

Both timestamp parameters receive the same formatted value.

Document that `read()` already uses distinct placeholders and remains unchanged.

## 10. Exact Future Runtime File Inventory

**Create:**
* `src/BehaviorTrace/Contract/BehaviorTraceAdminQueryInterface.php`
* `src/BehaviorTrace/DTO/BehaviorTraceAdminPageResultDTO.php`
* `src/BehaviorTrace/DTO/BehaviorTraceAdminQueryRequestDTO.php`
* `src/BehaviorTrace/Exception/BehaviorTraceAdminQueryExecutionException.php`
* `src/BehaviorTrace/Exception/BehaviorTraceAdminQueryInvalidArgumentException.php`
* `src/BehaviorTrace/Infrastructure/Mysql/Pagination/BehaviorTraceAdminQueryDescriptorBuilder.php`
* `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceAdminQueryMysqlRepository.php`
* `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceRowMapper.php`

**Modify:**
* `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceQueryMysqlRepository.php`

**Delete (Superseded Post-v1 Experiment):**
* `src/BehaviorTrace/Contract/BehaviorTracePaginatedQueryInterface.php`
* `src/BehaviorTrace/DTO/BehaviorTraceQueryCursorDTO.php`
* `src/BehaviorTrace/DTO/BehaviorTraceQueryPageDTO.php`
* `src/BehaviorTrace/Service/BehaviorTracePaginatedQueryService.php`

**Tests to create:**
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceAdminQueryRequestDTOTest.php`
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceAdminPageResultDTOTest.php`
* `tests/Unit/BehaviorTrace/Exception/BehaviorTraceAdminQueryInvalidArgumentExceptionTest.php`
* `tests/Unit/BehaviorTrace/Exception/BehaviorTraceAdminQueryExecutionExceptionTest.php`
* `tests/Unit/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceAdminQueryMysqlRepositoryTest.php`
* `tests/Unit/BehaviorTrace/Infrastructure/Mysql/Pagination/BehaviorTraceAdminQueryDescriptorBuilderTest.php`
* `tests/Unit/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceRowMapperTest.php`
* `tests/Regression/BehaviorTrace/BehaviorTraceQueryMysqlRepositoryRegressionTest.php`
* `tests/Integration/BehaviorTrace/BehaviorTraceAdminQueryMysqlRepositoryTest.php`
* `tests/Integration/BehaviorTrace/BehaviorTraceQueryMysqlRepositoryTest.php`

**Tests to modify:**
* `tests/Integration/BehaviorTrace/BehaviorTraceRepositoryTest.php` remains unchanged as the existing protected integration test unless a separately documented reason proves modification is unavoidable.

**Tests to delete (Superseded Post-v1 Experiment):**
* `tests/Unit/BehaviorTrace/Service/BehaviorTracePaginatedQueryServiceTest.php`
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceQueryCursorDTOTest.php`
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceQueryPageDTOTest.php`

**Documentation to update:**
* `EVENT_LOGGING_PACKAGE_REFERENCE.md`
* `docs/integration/ADMIN_READ_USAGE.md`
* `src/BehaviorTrace/README.md`
* `CHANGELOG.md`
* `docs/audits/DOCUMENTATION_INVENTORY.md`
* `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`
* `docs/architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md`

## 11. Complete Test Blueprint

### Unit
* request normalization and validation;
* UTF-8 length and invalid encoding;
* pair rules;
* result DTO JSON and iterator behavior;
* descriptor SQL and parameters;
* pagination configuration;
* exception codes/messages/previous throwable;
* row mapper and custom policy behavior;
* no SQLite dependency;
* no production test seam.

### Regression
Prove preservation of:
* `BehaviorTraceQueryInterface`;
* repository constructor;
* `BehaviorTraceQueryDTO`;
* `BehaviorTraceCursorDTO`;
* `find()` descending cursor behavior;
* `read()` ascending streaming behavior;
* existing limit behavior;
* existing storage exception messages;
* custom policy behavior;
* metadata and timestamp hydration behavior;
* absence of out-of-scope Admin Query APIs.

### Real MySQL Integration
Prove for Admin Query:
* every Admin Query filter;
* multiple filters;
* inclusive dates;
* zero rows;
* page normalization;
* per-page clamping;
* deterministic tie-breaker;
* total and filtered counts;
* nullable columns;
* native prepared statements;
* transaction non-ownership;
* new integration tests are not skipped.

The new primitive integration test must cover both:
* `find()` descending cursor behavior;
* `read()` ascending stream behavior;

using native prepared statements.

## Status and Approval Gate

- [x] no schema change is required or authorized;
- [x] no Composer change is required or authorized;
- [x] no SecuritySignals or AuthoritativeAudit work is authorized;
- [x] no tag or release is authorized;
- [x] the complete BehaviorTrace blueprint is approved;
- [x] BehaviorTrace Runtime implementation is authorized in a separate implementation PR;
- [x] deletion of the superseded post-v1 artifacts is authorized only atomically after the replacement Runtime and its complete tests pass.
- [x] approve this behavior-preserving primitive correction:

  ```sql
  (occurred_at < :cursor_at_before
   OR (occurred_at = :cursor_at_equal AND id < :cursor_id))
  ```

  * both timestamp placeholders receive the same formatted value;
  * `read()` remains unchanged;
  * public primitive signatures remain unchanged;
  * primitive query semantics remain unchanged;
  * native-prepared-statement MySQL Integration and Regression coverage are mandatory;
  * this approval does not authorize unrelated primitive redesign.
