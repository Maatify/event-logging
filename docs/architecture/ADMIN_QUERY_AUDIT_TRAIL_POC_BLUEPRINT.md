# Blueprint Drafted / Pending Owner Approval

* architecture only;
* no Runtime implementation authorized;
* no Composer change authorized;
* no schema change authorized;
* no old artifact deletion authorized yet.

## 1. Separate AuditTrail Admin Query Public Contract

The new Admin Query path must be completely separate from the protected `AuditTrailQueryInterface`.

* **Interface Name:** `AuditTrailAdminQueryInterface`
* **Namespace:** `Maatify\EventLogging\AuditTrail\Contract`
* **Filename:** `src/AuditTrail/Contract/AuditTrailAdminQueryInterface.php`

**Constructor / Methods:**
```php
namespace Maatify\EventLogging\AuditTrail\Contract;

use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminPageResultDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryExecutionException;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryInvalidArgumentException;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;

interface AuditTrailAdminQueryInterface
{
    /**
     * @throws AuditTrailAdminQueryInvalidArgumentException
     * @throws AuditTrailAdminQueryExecutionException
     * @throws AuditTrailStorageException
     */
    public function paginate(AuditTrailAdminQueryRequestDTO $request): AuditTrailAdminPageResultDTO;
}
```

**Why this complies:**
This contract relies entirely on package-owned request and result DTOs. It does not expose `maatify/persistence` boundaries directly, nor does it couple to any host application logic (like HTTP, controllers, or host-specific concepts), strictly adhering to the `PACKAGE_BUILDING_STANDARD.md` and the Admin Query architecture rules.

## 2. Exact Filter Contract

The AuditTrail Admin Query POC supports exactly these filters:
* `actorType`
* `actorId`
* `eventKey`
* `entityType`
* `entityId`
* `subjectType`
* `subjectId`
* `requestId`
* `correlationId`
* `after`
* `before`

### Type and ID Pair Rules
* **Type-only filtering is valid.** (e.g. `actorType` without `actorId`).
* **ID-only filtering is invalid.** (e.g. `actorId` without `actorType`).
* If ID-only input is provided, the constructor of the request DTO will reject it by throwing an exception.
* Subject type without subject ID is accepted.
* Subject ID without subject type is rejected.
* Empty type strings become `null`.
* Whitespace is trimmed before validation.
* Zero and negative IDs are rejected.

### String Rules
* Empty strings and whitespace-only strings are normalized to `null`.
* Trimmed non-empty strings are preserved.
* Maximum lengths adhere to actual schema sizes:
  * `actorType`: 32
  * `eventKey`: 255
  * `entityType`: 64
  * `subjectType`: 64
  * `requestId`: 64
  * `correlationId`: 36
* Invalid lengths throw an exception.
* Case sensitivity is left to the MySQL collation (`utf8mb4_unicode_ci`).

### Date Rules
* Accepted PHP type: `\DateTimeImmutable`
* Timezone expectations: Caller is responsible for providing UTC times, or they will be evaluated at their explicitly set timezone before being converted into string.
* Formatting used for MySQL parameters: `Y-m-d H:i:s.u`
* `after` inclusivity: `>=`
* `before` inclusivity: `<=`
* `after > before` throws an exception.
* Equal timestamps are valid.
* No mutation of caller-provided `DateTimeImmutable` values.

## 3. Page and Sort Contract

Delegation to `Maatify\Persistence\Pdo\Pagination` is used.

**Configuration Details:**
The exact persistence configuration is:
```php
$this->paginationConfig = new PaginationConfig(
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
```

* `defaultPage` does not exist in `PaginationConfig`. Page normalization to `1` is exclusively owned by `PdoPaginator`.
* `occurred_at` is the only caller-selectable sort key.
* `id` exists only as an internally trusted tie-breaker key. It must be in the whitelist to function as a valid tie-breaker, but it is not a valid caller sort key.
* Caller-supplied `sortBy: 'id'` must be normalized to `null` before creating the persistence `PageRequest`.
* The complete internal whitelist is associative, mapping public string keys to internal database identifiers.

## 4. Three-Query SQL Model

We will build internal statements mapping to `totalSql`, `filteredCountSql`, and `dataSql`.
Target table: `maa_event_logging_audit_trail`

### Total Meaning
`totalSql` counts all records unconditionally:
`SELECT COUNT(*) FROM maa_event_logging_audit_trail`

Because there is no mandatory tenant isolation or soft-delete state.

### Filtered Meaning
`filteredCountSql` applies every domain filter provided by the request:
`SELECT COUNT(*) FROM maa_event_logging_audit_trail WHERE ...`

### Data Meaning
`dataSql` selects specific columns with identical conditions to `filteredCountSql`.

**Semantic Source:**
`Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\Pagination\AuditTrailAdminQueryDescriptorBuilder`
* **Filename:** `src/AuditTrail/Infrastructure/Mysql/Pagination/AuditTrailAdminQueryDescriptorBuilder.php`
* **Namespace:** `Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\Pagination`
* **Class modifier:** `/** @internal */ final class`
* **Constructor:** `public function __construct()`
* **Methods:**
```php
public function build(AuditTrailAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor;

/**
 * @return array{
 *     whereSql: string,
 *     params: array<string, string|int|bool|null>
 * }
 */
/**
 * @return array{
 *     whereSql: string,
 *     params: array<string, string|int|bool|null>
 * }
 */
private function buildFilteredWhereAndParams(
    AuditTrailAdminQueryRequestDTO $request
): array;
```

**Implementation Rules:**
* `buildFilteredWhereAndParams()` must build the conditions and parameters once.
* Dates must be converted to UTC before `Y-m-d H:i:s.u` formatting inside the descriptor builder:
  ```php
  $request->after?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
  ```
* The exact filter-to-SQL mapping must be used:
  * `actorType` → `actor_type = :actor_type`
  * `actorId` → `actor_id = :actor_id`
  * `eventKey` → `event_key = :event_key`
  * `entityType` → `entity_type = :entity_type`
  * `entityId` → `entity_id = :entity_id`
  * `subjectType` → `subject_type = :subject_type`
  * `subjectId` → `subject_id = :subject_id`
  * `requestId` → `request_id = :request_id`
  * `correlationId` → `correlation_id = :correlation_id`
  * `after` → `occurred_at >= :after`
  * `before` → `occurred_at <= :before`
* Provide the complete method flow:
```php
$conditions = [];
$params = [];

if ($request->actorType !== null) {
    $conditions[] = 'actor_type = :actor_type';
    $params['actor_type'] = $request->actorType;
}

if ($request->actorId !== null) {
    $conditions[] = 'actor_id = :actor_id';
    $params['actor_id'] = $request->actorId;
}

if ($request->eventKey !== null) {
    $conditions[] = 'event_key = :event_key';
    $params['event_key'] = $request->eventKey;
}

if ($request->entityType !== null) {
    $conditions[] = 'entity_type = :entity_type';
    $params['entity_type'] = $request->entityType;
}

if ($request->entityId !== null) {
    $conditions[] = 'entity_id = :entity_id';
    $params['entity_id'] = $request->entityId;
}

if ($request->subjectType !== null) {
    $conditions[] = 'subject_type = :subject_type';
    $params['subject_type'] = $request->subjectType;
}

if ($request->subjectId !== null) {
    $conditions[] = 'subject_id = :subject_id';
    $params['subject_id'] = $request->subjectId;
}

if ($request->requestId !== null) {
    $conditions[] = 'request_id = :request_id';
    $params['request_id'] = $request->requestId;
}

if ($request->correlationId !== null) {
    $conditions[] = 'correlation_id = :correlation_id';
    $params['correlation_id'] = $request->correlationId;
}

if ($request->after !== null) {
    $conditions[] = 'occurred_at >= :after';
    $params['after'] = $request->after
        ->setTimezone(new \DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s.u');
}

if ($request->before !== null) {
    $conditions[] = 'occurred_at <= :before';
    $params['before'] = $request->before
        ->setTimezone(new \DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s.u');
}

$whereSql = $conditions === []
    ? ''
    : ' WHERE ' . implode(' AND ', $conditions);

return [
    'whereSql' => $whereSql,
    'params' => $params,
];
```

* Then define `build()` exactly:
```php
$filtered = $this->buildFilteredWhereAndParams($request);
$whereSql = $filtered['whereSql'];
$params = $filtered['params'];

$totalSql = 'SELECT COUNT(*) FROM maa_event_logging_audit_trail';
$filteredCountSql =
    'SELECT COUNT(*) FROM maa_event_logging_audit_trail'
    . $whereSql;
$dataSql =
    'SELECT id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, '
    . 'subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, '
    . 'correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at '
    . 'FROM maa_event_logging_audit_trail'
    . $whereSql;

return new PdoPaginationQueryDescriptor(
    totalSql: $totalSql,
    totalParams: [],
    filteredCountSql: $filteredCountSql,
    filteredCountParams: $params,
    dataSql: $dataSql,
    dataParams: $params,
);
```

**Explicit Columns:**
No `SELECT *`. The `dataSql` explicitly selects:
`id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at`

**Parameter Contract:**
* Exact placeholders: `actor_type`, `actor_id`, `event_key`, `entity_type`, `entity_id`, `subject_type`, `subject_id`, `request_id`, `correlation_id`, `after`, `before`.
* No leading colons in array keys.
* Named placeholders only.
* Unique per SQL statement.
* Values map strictly to: `string|int|bool|null`. Dates are stringified. No `DateTimeImmutable` passed directly to persistence.

## 5. Mapper Extraction Strategy

**Path:** `src/AuditTrail/Infrastructure/Mysql/AuditTrailRowMapper.php`
**Namespace:** `Maatify\EventLogging\AuditTrail\Infrastructure\Mysql`
**Modifier:** `/** @internal */ final class`
**Method:** `public function map(array $row): AuditTrailViewDTO`

Constructed manually within `AuditTrailQueryMysqlRepository` and the new `AuditTrailAdminQueryMysqlRepository`.
The primitive repository constructor is preserved exactly as:
`public function __construct(\PDO $pdo)`
Inside the constructor: `$this->mapper = new AuditTrailRowMapper();`

The extraction guarantees 100% hydration compatibility. No factories or bindings require updates.

## 6. Domain-Owned Result Adaptation

**Class:** `AuditTrailAdminPageResultDTO`
**Namespace:** `Maatify\EventLogging\AuditTrail\DTO`
**Path:** `src/AuditTrail/DTO/AuditTrailAdminPageResultDTO.php`
**Modifiers:** `final readonly class`
**Implements:** `\IteratorAggregate<int, AuditTrailViewDTO>`, `\JsonSerializable`

```php
    /**
     * @param list<AuditTrailViewDTO> $items
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
    ) {}

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
```

JSON keys strictly match the property names (`items`, not `data`).

## 7. Request DTO Contract

**Class:** `AuditTrailAdminQueryRequestDTO`
**Namespace:** `Maatify\EventLogging\AuditTrail\DTO`
**Path:** `src/AuditTrail/DTO/AuditTrailAdminQueryRequestDTO.php`
**Modifiers:** `final readonly class`
**Implements:** `\JsonSerializable`

**Constructor Signature & Properties:**
Normalized values are assigned exactly once to separately declared readonly properties.

```php
    public ?string $actorType;
    public ?int $actorId;
    public ?string $eventKey;
    public ?string $entityType;
    public ?int $entityId;
    public ?string $subjectType;
    public ?int $subjectId;
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
        ?string $eventKey = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?\DateTimeImmutable $after = null,
        ?\DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null
    ) {
        $this->actorType = self::normalizeNullableString($actorType, 'actorType', 32);
        $this->actorId = self::validatePositiveNullableId($actorId, 'actorId');
        // validate pairs
        if ($this->actorId !== null && $this->actorType === null) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId('actorId without actorType');
        }

        $this->eventKey = self::normalizeNullableString($eventKey, 'eventKey', 255);
        $this->entityType = self::normalizeNullableString($entityType, 'entityType', 64);
        $this->entityId = self::validatePositiveNullableId($entityId, 'entityId');
        if ($this->entityId !== null && $this->entityType === null) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId('entityId without entityType');
        }

        $this->subjectType = self::normalizeNullableString($subjectType, 'subjectType', 64);
        $this->subjectId = self::validatePositiveNullableId($subjectId, 'subjectId');
        if ($this->subjectId !== null && $this->subjectType === null) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId('subjectId without subjectType');
        }

        $this->requestId = self::normalizeNullableString($requestId, 'requestId', 64);
        $this->correlationId = self::normalizeNullableString($correlationId, 'correlationId', 36);

        $this->after = $after;
        $this->before = $before;
        if ($this->after !== null && $this->before !== null && $this->after > $this->before) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidDateRange();
        }

        $this->page = $page;
        $this->perPage = $perPage;

        $normalizedSortBy = self::normalizeNullableString($sortBy, 'sortBy', 64);
        $this->sortBy = $normalizedSortBy === 'occurred_at' ? 'occurred_at' : null;

        $normalizedSortDirection = strtoupper((string)self::normalizeNullableString($sortDirection, 'sortDirection', 4));
        $this->sortDirection = in_array($normalizedSortDirection, ['ASC', 'DESC'], true) ? $normalizedSortDirection : null;
    }

    private static function utf8Length(string $value, string $field): int
    {
        $length = preg_match_all('/./us', $value);

        if ($length === false) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        return $length;
    }

    private static function normalizeNullableString(
        ?string $value,
        string $field,
        int $maxLength
    ): ?string {
        if ($value === null) return null;
        $trimmed = trim($value);
        if ($trimmed === '') return null;
        if (self::utf8Length($trimmed, $field) > $maxLength) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidLength($field);
        }
        return $trimmed;
    }

    private static function validatePositiveNullableId(
        ?int $value,
        string $field
    ): ?int {
        if ($value === null) return null;
        if ($value <= 0) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId($field);
        }
        return $value;
    }

    public function jsonSerialize(): array
    {
        return [
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'eventKey' => $this->eventKey,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
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
```

Construct-time validation is performed immediately. No validators delegated. Page and per-page are passed raw without local numeric normalization. MySQL date string formatting occurs exclusively inside the descriptor builder, not the DTO json layer. Note that string length limits match the character semantics of the current `utf8mb4` `VARCHAR`/`CHAR` schema without requiring `ext-mbstring`. The `invalidEncoding(string $field)` must be added to the exact named-constructor plan and its tests.

## 8. Exception Boundary

**Exception Recommendation:**
Before AuditTrail Admin Query Runtime implementation, a separate Owner-approved package-wide compatibility PR must introduce a unified package exception marker `Maatify\EventLogging\Exception\EventLoggingExceptionInterface` that extends `\Throwable`. All existing package-defined EventLogging exceptions must implement the marker directly or indirectly without changing their existing constructors, messages, error codes, or failure behavior. A partial AuditTrail-only marker strategy is prohibited.

This prerequisite must update exactly the following existing package-defined exceptions to implement the marker **directly** (because no package-owned common exception base currently exists):
* `src/AuditTrail/Exception/AuditTrailStorageException.php` (`AuditTrailStorageException`)
* `src/AuthoritativeAudit/Exception/AuthoritativeAuditStorageException.php` (`AuthoritativeAuditStorageException`)
* `src/BehaviorTrace/Exception/BehaviorTraceStorageException.php` (`BehaviorTraceStorageException`)
* `src/DeliveryOperations/Exception/DeliveryOperationsStorageException.php` (`DeliveryOperationsStorageException`)
* `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryStorageException.php` (`DiagnosticsTelemetryStorageException`)
* `src/SecuritySignals/Exception/SecuritySignalsStorageException.php` (`SecuritySignalsStorageException`)

For each of these exceptions, their constructor, message, error code (`ErrorCodeEnum::DATABASE_CONNECTION_FAILED`), and failure behavior must remain entirely unchanged.

Prerequisite tests needed to prove package-wide marker compliance must include this exact test:
`tests/Unit/Exception/EventLoggingExceptionInterfaceTest.php`
Class:
`Maatify\EventLogging\Tests\Unit\Exception\EventLoggingExceptionInterfaceTest`

The test must prove for all six exceptions:
* instance of `EventLoggingExceptionInterface`;
* instance of `SystemMaatifyException`;
* existing default error code remains `DATABASE_CONNECTION_FAILED`;
* existing construction and previous-throwable behavior remain unchanged.

Also add the root package-reference update required by the standard:
`EVENT_LOGGING_PACKAGE_REFERENCE.md`
as part of the separate prerequisite PR, not PR #96.

This POC implementation remains **blocked** until that prerequisite decision is approved and completed.

After the prerequisite is implemented, the Admin Query exception structure is:

1. `AuditTrailAdminQueryInvalidArgumentException`
   - Implements the package marker.
   - Uses `ErrorCodeEnum::INVALID_ARGUMENT`.
   - Used for filter, ID, length, date validation, and pair rules.
2. `AuditTrailAdminQueryExecutionException`
   - Implements the package marker.
   - Uses `ErrorCodeEnum::MAATIFY_ERROR`.
   - Used for invalid pagination configuration and invalid descriptor construction.
3. `AuditTrailStorageException` (existing v1 exception)
   - `PDOException` and `PaginationExecutionException` from `maatify/persistence` are translated to `AuditTrailStorageException`.
   - Uses `ErrorCodeEnum::DATABASE_CONNECTION_FAILED`.
   - Preserves the original throwable as `previous`.

Unexpected mapper `Throwable` propagates unchanged unless it is explicitly classified as an AuditTrail storage/hydration failure. Every translated throwable is preserved as `previous`. Nothing is swallowed. No transaction is started, committed, or rolled back.

## 9. Dependency Injection and Construction

**Admin Repository Construction:**
`Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailAdminQueryMysqlRepository`
```php
    private AuditTrailRowMapper $mapper;
    private AuditTrailAdminQueryDescriptorBuilder $descriptorBuilder;
    private PdoPaginator $paginator;

    public function __construct(private \PDO $pdo)
    {
        $this->mapper = new AuditTrailRowMapper();
        $this->descriptorBuilder = new AuditTrailAdminQueryDescriptorBuilder();
        $this->paginator = new PdoPaginator();

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

    public function paginate(AuditTrailAdminQueryRequestDTO $request): AuditTrailAdminPageResultDTO
    {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection
        );

        try {
            $descriptor = $this->descriptorBuilder->build($request);
            $paginationConfig = $this->createPaginationConfig();

            $result = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $paginationConfig,
                fn (array $row): AuditTrailViewDTO => $this->mapper->map($row),
            );
        } catch (\Maatify\Persistence\Exception\PaginationExecutionException|\PDOException $e) {
            throw new AuditTrailStorageException(
                message: "Failed to query audit trail: " . $e->getMessage(),
                previous: $e
            );
        } catch (\Maatify\Persistence\Exception\InvalidPaginationConfigurationException|\Maatify\Persistence\Exception\InvalidPaginationQueryException $e) {
            throw AuditTrailAdminQueryExecutionException::executionFailed($e);
        }

        return new AuditTrailAdminPageResultDTO(
            items: $result->data,
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
```

## 10. Exact Runtime File Plan

| Class/File | Action | Responsibility | Public API/Compat Impact |
| --- | --- | --- | --- |
| `src/Exception/EventLoggingExceptionInterface.php` | NEW (Prerequisite) | Unified package marker | Strictly additive |
| `src/AuditTrail/Contract/AuditTrailAdminQueryInterface.php` | NEW | Admin public contract | None (Additive) |
| `src/AuditTrail/DTO/AuditTrailAdminQueryRequestDTO.php`| NEW | Request DTO | None (Additive) |
| `src/AuditTrail/DTO/AuditTrailAdminPageResultDTO.php` | NEW | Result DTO | None (Additive) |
| `src/AuditTrail/Infrastructure/Mysql/AuditTrailAdminQueryMysqlRepository.php`| NEW | Repository executing API | None (Additive) |
| `src/AuditTrail/Infrastructure/Mysql/Pagination/AuditTrailAdminQueryDescriptorBuilder.php`| NEW | Builds internal statements| None (Additive) |
| `src/AuditTrail/Infrastructure/Mysql/AuditTrailRowMapper.php` | NEW | Internal row mapping | None (Strictly preserves DTO hydration) |
| `src/AuditTrail/Exception/AuditTrailAdminQueryExecutionException.php` | NEW | Query execution boundary | None (Additive) |
| `src/AuditTrail/Exception/AuditTrailAdminQueryInvalidArgumentException.php` | NEW | Query validation boundary | None (Additive) |
| `src/AuditTrail/Contract/AuditTrailQueryInterface.php` | UNCHANGED | Primitive Interface | Protects v1.0 api |
| `src/AuditTrail/DTO/AuditTrailQueryDTO.php` | UNCHANGED | Primitive Interface DTO | Protects v1.0 api |
| `src/AuditTrail/DTO/AuditTrailViewDTO.php` | UNCHANGED | Protected public v1 DTO | Protects v1.0 api |
| `src/AuditTrail/Infrastructure/Mysql/AuditTrailQueryMysqlRepository.php`| MODIFY | Internally construct shared mapper while preserving __construct(PDO $pdo) | Maintains 100% backwards compatibility |
| `src/AuditTrail/Exception/AuditTrailStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |
| `src/AuthoritativeAudit/Exception/AuthoritativeAuditStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |
| `src/BehaviorTrace/Exception/BehaviorTraceStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |
| `src/DeliveryOperations/Exception/DeliveryOperationsStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |
| `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |
| `src/SecuritySignals/Exception/SecuritySignalsStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |
| `src/AuditTrail/Contract/AuditTrailPaginatedQueryInterface.php`| DELETE | Obsolete architecture | Break for POC users, as intended |
| `src/AuditTrail/DTO/AuditTrailQueryCursorDTO.php` | DELETE | Obsolete architecture | Break for POC users, as intended |
| `src/AuditTrail/DTO/AuditTrailQueryPageDTO.php` | DELETE | Obsolete architecture | Break for POC users, as intended |
| `src/AuditTrail/Service/AuditTrailPaginatedQueryService.php`| DELETE | Obsolete architecture | Break for POC users, as intended |
| `tests/Unit/AuditTrail/Service/AuditTrailPaginatedQueryServiceTest.php`| DELETE| Obsolete architecture | - |
| `composer.json` | MODIFY | Add dependency | Additive |
| `composer.lock` | UNCHANGED | Should not be present | Protects release strategy |
| `EVENT_LOGGING_PACKAGE_REFERENCE.md` | MODIFY | Document new API | Additive |
| `docs/integration/ADMIN_READ_USAGE.md`| MODIFY | Document new API | Additive |
| `src/AuditTrail/README.md` | MODIFY | Document new API | Additive |
| `CHANGELOG.md` | MODIFY | Record release | Additive |
| `docs/audits/DOCUMENTATION_INVENTORY.md`| MODIFY | Keep inventory updated | Additive |
| `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`| MODIFY | Keep roadmap updated | Additive |
| `tests/Unit/AuditTrail/DTO/AuditTrailAdminQueryRequestDTOTest.php` | NEW | Verify request validation logic | Additive |
| `tests/Unit/AuditTrail/DTO/AuditTrailAdminPageResultDTOTest.php` | NEW | Verify DTO serialization | Additive |
| `tests/Unit/AuditTrail/Infrastructure/Mysql/Pagination/AuditTrailAdminQueryDescriptorBuilderTest.php` | NEW | Verify SQL generation logic | Additive |
| `tests/Unit/AuditTrail/Infrastructure/Mysql/AuditTrailRowMapperTest.php` | NEW | Verify mapping extraction | Additive |
| `tests/Unit/AuditTrail/Infrastructure/Mysql/AuditTrailAdminQueryMysqlRepositoryTest.php` | NEW | Verify repository execution bounds | Additive |
| `tests/Unit/AuditTrail/Exception/AuditTrailAdminQueryExceptionTest.php` | NEW | Verify invalid/execution exceptions | Additive |
| `tests/Regression/AuditTrail/AuditTrailQueryMysqlRepositoryRegressionTest.php` | NEW | Verify protected v1 API remains completely unchanged | Additive |
| `tests/Integration/AuditTrail/AuditTrailRepositoryTest.php` | UNCHANGED | UNCHANGED / REGRESSION-PROTECTED | Protects v1.0 api |
| `tests/Integration/AuditTrail/AuditTrailAdminQueryMysqlRepositoryTest.php`| NEW | Real MySQL Integration Test | Additive |
| `tests/Integration/AuditTrail/AuditTrailQueryMysqlRepositoryTest.php` | NEW | Ensure primitive unchanged via real DB test | Additive |

## 11. Atomic Retirement Sequence

The implementation PR must follow this exact sequence:
1. Complete Owner-approved package exception marker prerequisite PR.
2. add `maatify/persistence ^1.1.0`;
3. add domain Admin Query contracts;
4. add filter and descriptor construction;
5. extract shared row mapping;
6. add Admin Query execution path;
7. add result adaptation;
8. add exception translation;
9. add complete Unit tests;
10. add complete Regression tests;
11. add real MySQL Integration tests;
12. prove primitive cursor compatibility;
13. update construction/factories/bindings only where required;
14. delete superseded AuditTrail pagination artifacts;
15. delete their obsolete tests;
16. update documentation;
17. run full validation.

## 12. Complete Test Matrix

### Unit Tests
* request constructor/defaults;
* empty-string normalization;
* whitespace trimming;
* positive-ID validation;
* type/ID pair rules;
* type-only filters;
* ID-without-type behavior;
* valid equal date boundaries;
* invalid date ranges;
* inclusive date semantics;
* page raw values;
* per-page raw values;
* sort raw values;
* descriptor SQL;
* explicit selected columns;
* no `SELECT *`;
* no `ORDER BY` in `dataSql`;
* no `LIMIT`;
* no `OFFSET`;
* exact parameter names;
* exact parameter maps;
* no leading-colon keys;
* no reserved prefix;
* separate total/filter/data params;
* sort whitelist configuration;
* tie-breaker configuration;
* result adaptation;
* serialized result shape;
* mapper valid metadata;
* mapper corrupt metadata;
* mapper invalid date fallback;
* nullable IDs;
* exception translation;
* marker-interface compliance;
* original throwable preservation;
* primitive unchanged constructor parameters (`PDO`).

### Regression Tests
* exact primitive interface signature unchanged;
* exact primitive query DTO constructor unchanged;
* primitive cursor fields unchanged;
* primitive default limit unchanged;
* primitive ordering remains: `occurred_at DESC, id DESC`;
* primitive date inclusivity unchanged;
* primitive repository return type unchanged;
* primitive hydration unchanged;
* primitive exception class unchanged;
* primitive repository constructor unchanged (`PDO`);
* AuditTrail write path unchanged;
* schema unchanged;
* no old wrapper classes after replacement;
* no generic Admin Query interface;
* no generic cross-domain query repository;
* no HTTP or framework namespace;
* no `composer.lock`;
* package reference contains new API only after implementation;
* constructor parameter names and order protected where appropriate.

### Real MySQL Integration Tests
* no-filter total;
* no-filter filtered;
* no-filter data;
* actor type;
* actor type plus actor ID;
* event key;
* entity type;
* entity type plus entity ID;
* subject type;
* subject type plus subject ID;
* request ID;
* correlation ID;
* after boundary;
* before boundary;
* equal after/before where valid;
* multiple simultaneous filters;
* zero filtered rows;
* first page;
* later page;
* page overflow resets according to persistence contract;
* per-page clamping;
* default sort;
* requested sort;
* invalid sort fallback;
* duplicate timestamps ordered by unique ID tie-breaker;
* explicit selected-column mapping;
* valid metadata JSON;
* corrupt metadata fallback;
* nullable database columns;
* total/filter/data semantic alignment;
* separate parameter maps;
* native prepared statements;
* persistence configuration failure translation;
* execution failure translation;
* real `PDOException` behavior;
* success inside caller-owned transaction;
* transaction remains active after success;
* no transaction started outside a caller transaction.

## 13. Standards Compliance Matrix

| Area | Governing File & Section | Required Rule | Proposed Blueprint Decision | Evidence | Conflict Status |
| --- | --- | --- | --- | --- | --- |
| Package/Domain Isolation | `PACKAGE_BUILDING_STANDARD.md` | Keep domains separate | New namespace isolated to `Maatify\EventLogging\AuditTrail` | `src/AuditTrail/Contract` | No Conflict |
| Public Admin Query Interface | `ADMIN_QUERY_API_ARCHITECTURE.md` | Framework agnostic read models | `AuditTrailAdminQueryInterface` has no HTTP or Framework bindings | Uses standard PHP | Blocked Pending Owner Approval (Exception Hierarchy) |
| Request DTO | `PACKAGE_BUILDING_STANDARD.md` | Strict type validation | `AuditTrailAdminQueryRequestDTO` validates and assigns readonly properties in constructor | Constructor logic | No Conflict |
| Result DTO | `PACKAGE_BUILDING_STANDARD.md` | Implement JSON & Iterators | `AuditTrailAdminPageResultDTO` does both | `getIterator()` and `jsonSerialize()` methods | No Conflict |
| Offset Pagination | `ADMIN_QUERY_API_ROADMAP.md` | Offset instead of cursor | Delegates pagination logic to persistence package | Uses PDO Pagination | No Conflict |
| Persistence Delegation | `PACKAGE_BUILDING_STANDARD.md` | Externalize generic concerns | Using `maatify/persistence` for the generic page logic | `PdoPaginator::paginate()` | No Conflict |
| Filter Ownership | `ADMIN_QUERY_API_ARCHITECTURE.md` | Event Logging owns filters | Filters built using dedicated Builder class | `AuditTrailAdminQueryDescriptorBuilder` | No Conflict |
| SQL Ownership | `ADMIN_QUERY_API_ARCHITECTURE.md` | SQL built in package | Defined inside Repository/Builder | Explicit Query definitions | No Conflict |
| Semantic Count/Data Alignment | `ADMIN_QUERY_API_ARCHITECTURE.md`| Conditions match | Same builder method for count/data filters | `buildFilteredWhereAndParams()` | No Conflict |
| Explicit Selected Columns | `PACKAGE_BUILDING_STANDARD.md` | No `SELECT *` | Explicitly lists all 19 columns | Defined in SQL string | No Conflict |
| Deterministic Sorting | `PACKAGE_BUILDING_STANDARD.md` | Restrict sort options | Caller selectable only `occurred_at` | Enforced by PDO Paginator config | No Conflict |
| Tie-breaker | `PACKAGE_BUILDING_STANDARD.md` | Guarantee sorting order | Tie break using `id` | Enforced by internal `SortWhitelist` | No Conflict |
| Mapper Extraction | `PACKAGE_BUILDING_STANDARD.md` | No duplicate logic | `AuditTrailRowMapper` shared by both repos | Row Mapper internal | No Conflict |
| Exception Hierarchy | `PACKAGE_BUILDING_STANDARD.md` | Implement package marker | Implement global marker | Requires prerequisite Owner Approval | Blocked Pending Owner Approval (Exception Hierarchy) |
| Dependency Direction | `PACKAGE_BUILDING_STANDARD.md` | Outward dependencies only | Relies exclusively on core maatify deps | Architecture rules | No Conflict |
| Composer Impact | `COMPOSER_PACKAGE_STANDARD.md` | Validate dependencies | Adds `maatify/persistence` | Composer require update | No Conflict |
| No `composer.lock` | `COMPOSER_PACKAGE_STANDARD.md` | Lock must not be tracked | Lock omitted | Not committed | No Conflict |
| Unit Tests | `TESTING_STRATEGY.md` | Cover logic fully | Defined explicitly | Unit Test Matrix | No Conflict |
| Regression Tests | `TESTING_STRATEGY.md` | Prove V1 API preserved | Defined explicitly | Regression Matrix | No Conflict |
| MySQL Integration Tests| `TESTING_STRATEGY.md` | Cover DB Queries | Defined explicitly | Integration Matrix | No Conflict |
| PHPStan Max-Level | `PACKAGE_BUILDING_STANDARD.md` | Full types, no ignore | Adhering fully without suppressions | Strict DTO types | No Conflict |
| CI Compliance | `CI_WORKFLOW_STANDARD.md` | Automated execution | CI Gate commands required | Execution listed | No Conflict |
| Package-Reference Update | `LIBRARY_PRESENTATION_STANDARD.md`| Update documentation | Checked | Final PR requirement | No Conflict |
| Changelog Update | `LIBRARY_PRESENTATION_STANDARD.md`| Maintain structure | Checked | PR tracking | No Conflict |
| No Framework-Specific API | `PACKAGE_BUILDING_STANDARD.md` | Agnostic contracts | Clean implementation | No Controllers | No Conflict |
| Old-Artifact Retirement | `ADMIN_QUERY_API_ROADMAP.md` | Obsolete POC Removal | Exact file list added | Covered in retirement | No Conflict |

### Standards Conflict Discovered
**Conflict:** The `PACKAGE_BUILDING_STANDARD.md` requires domain exceptions to have a package exception marker interface or unified root to reliably catch package-owned exceptions. However, the protected `v1.0.0` EventLogging exception hierarchy does not expose a unified package marker consistently. This POC cannot unilaterally introduce a partial marker strategy in a single domain without creating inconsistency across the package.
**Impact:** Final exception hierarchy implementation for this POC is blocked pending an Owner-level compatibility decision on how to align the EventLogging package exception strategy with the new Standard without breaking `v1.0.0` backwards compatibility. A prerequisite PR must be approved and merged before this POC can proceed.
**Blueprint Decision:** The blueprint defines exact exception translation, but marks implementation **Blocked Pending Owner Approval**.

## 14. Validation Gate

The later implementation PR must require successful execution of:
```bash
composer validate --strict
composer dump-autoload --optimize --strict-psr
composer analyse
composer test
composer test:unit
composer test:regression
composer test:integration
git diff --check
```

Exact testing commands for implementation:
```bash
vendor/bin/phpunit tests/Unit/AuditTrail
vendor/bin/phpunit tests/Regression/AuditTrail
vendor/bin/phpunit tests/Integration/AuditTrail/AuditTrailAdminQueryMysqlRepositoryTest.php
```

## 15. Composer and Release Impact
* future addition: `maatify/persistence ^1.1.0`
* exact placement in require;
* no `composer.lock`;
* no schema migration expected;
* new additive public Admin Query API;
* removal of unreleased superseded post-v1 artifacts;
* compatibility with protected `v1.0.0` primitive contracts;
* expected Semantic Versioning impact: minor version update;
* expected future event-logging release: `v1.1.0`;
* no tag or release during blueprint work;
* no tag or release during implementation review unless separately approved;
* Packagist publication remains Owner-controlled.

## 16. Explicit Non-Goals
* no Runtime implementation;
* no Composer change;
* no schema change;
* no primitive cursor replacement;
* no primitive query DTO modification;
* no generic repository;
* no generic cross-domain Admin Query API;
* no reporting;
* no dashboard summaries;
* no HTTP;
* no routes;
* no controllers;
* no middleware;
* no permissions;
* no localization;
* no exports;
* no name resolution;
* no joins;
* no metadata search;
* no free-text search;
* no caching;
* no approximate counts;
* no transaction ownership;
* no keyset pagination;
* no cursor pagination for the new Admin API;
* no implementation for the other five domains;
* no tag;
* no release.

## 17. Owner Approval Checklist

* [ ] public interface name and signature;
* [ ] request DTO;
* [ ] result DTO;
* [ ] serialization shape;
* [ ] filter list;
* [ ] pair rules;
* [ ] empty-string rules;
* [ ] ID validation;
* [ ] date rules;
* [ ] page defaults;
* [ ] per-page limits;
* [ ] sort whitelist;
* [ ] tie-breaker;
* [ ] total meaning;
* [ ] filtered meaning;
* [ ] SQL semantic-alignment design;
* [ ] parameter contract;
* [ ] mapper extraction;
* [ ] exception translation;
* [ ] construction/factory plan;
* [ ] exact Runtime file inventory;
* [ ] exact deletion list;
* [ ] test matrix;
* [ ] Composer dependency;
* [ ] Semantic Versioning impact;
* [ ] documentation update plan;
* [ ] standards compliance matrix.

Runtime implementation remains blocked until the Owner explicitly approves this blueprint.