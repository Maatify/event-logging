# DeliveryOperations Admin Query Blueprint

**Status:** Proposed / Pending Owner Approval
**Runtime Not Authorized**

This document defines the complete proposed contract for adding the new Admin Query API path for `DeliveryOperations`.

## 1. Scope and Design Principle
The DeliveryOperations domain has no superseded post-v1 pagination experiment to rebuild. This is a new post-v1 implementation.
The goal is to provide a complete, package-owned Admin filtering capability over every persisted field in `maa_event_logging_delivery_operations`. The package exposes these capabilities; the host application decides what to use.

## 2. Protected Runtime Boundary
The complete published `v1.0.0` DeliveryOperations Runtime is strictly preserved:
- writer and recorder behavior;
- primitive query interface and DTO (`DeliveryOperationsQueryInterface`, `DeliveryOperationsQueryDTO`);
- view DTO (`DeliveryOperationsViewDTO`);
- primitive repository constructor and behavior;
- current schema (`schema.maa_event_logging_delivery_operations.sql`);
- policy behavior;
- factory/provider/bindings;
- storage exception boundary;
- fail-open recorder boundary;
- caller-owned transactions.

No primitive corrections (e.g., placeholder distinction) are authorized in this PR.

## 3. Public Admin Query Contracts

### 3.1 Interface
`DeliveryOperationsAdminQueryInterface`
```php
namespace Maatify\EventLogging\DeliveryOperations\Contract;

use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminPageResultDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryExecutionException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;

interface DeliveryOperationsAdminQueryInterface
{
    /**
     * @throws DeliveryOperationsStorageException
     * @throws DeliveryOperationsAdminQueryExecutionException
     * @throws DeliveryOperationsAdminQueryInvalidArgumentException
     */
    public function paginate(DeliveryOperationsAdminQueryRequestDTO $request): DeliveryOperationsAdminPageResultDTO;
}
```

### 3.2 Exceptions
- `DeliveryOperationsAdminQueryInvalidArgumentException`: extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException`, implements `EventLoggingExceptionInterface`. Emits domain-specific validation error messages.
  - Named factories required:
    - `invalidId(string $field): self` -> `Invalid DeliveryOperations Admin Query ID: {field}`
    - `invalidRetryRange(): self` -> `Invalid DeliveryOperations Admin Query retry range: attempt_no_min must be less than or equal to attempt_no_max`
    - `invalidLength(string $field): self` -> `Invalid DeliveryOperations Admin Query length: {field}`
    - `invalidEncoding(string $field): self` -> `Invalid DeliveryOperations Admin Query UTF-8 encoding: {field}`
    - `invalidDateRange(string $rangeName): self` -> `Invalid DeliveryOperations Admin Query date range: {rangeName} after must be before or equal to before`
    - `invalidNullState(string $field): self` -> `Invalid DeliveryOperations Admin Query null-state input: {field}`
    - `invalidErrorMessageSearch(): self` -> `Invalid DeliveryOperations Admin Query error_message search input`
    - `invalidMetadataPath(): self` -> `Invalid DeliveryOperations Admin Query metadata path or shape`
- `DeliveryOperationsAdminQueryExecutionException`: extends `Maatify\Exceptions\Exception\System\SystemMaatifyException`, implements `EventLoggingExceptionInterface`. Uses `ErrorCodeEnum::MAATIFY_ERROR`. Emits domain-specific execution messages.
  - Named factory required:
    - `executionFailed(\Throwable $previous): self` -> `DeliveryOperations Admin Query execution failed: {previous message}`

### 3.3 Request DTO
`DeliveryOperationsAdminQueryRequestDTO`
Constructed with all valid filter dimensions. Strings must be trimmed and UTF-8 validated. Short invalid sort values map to `null`.
```php
namespace Maatify\EventLogging\DeliveryOperations\DTO;

use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;

final readonly class DeliveryOperationsAdminQueryRequestDTO implements \JsonSerializable
{
    public function __construct(
        public int|string|null $page = null,
        public int|string|null $perPage = null,
        public ?string $sortBy = null,
        public ?string $sortDirection = null,
        public ?string $eventId = null,
        public ?string $channel = null,
        public ?string $operationType = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?string $status = null,
        public ?int $attemptNoMin = null,
        public ?int $attemptNoMax = null,
        public ?string $correlationId = null,
        public ?string $requestId = null,
        public ?string $provider = null,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessageLike = null,
        public ?array $metadataFilters = null,
        public ?\DateTimeImmutable $scheduledAfter = null,
        public ?\DateTimeImmutable $scheduledBefore = null,
        public ?\DateTimeImmutable $completedAfter = null,
        public ?\DateTimeImmutable $completedBefore = null,
        public ?\DateTimeImmutable $occurredAfter = null,
        public ?\DateTimeImmutable $occurredBefore = null,
        public ?int $id = null,
        public ?array $nullStateFilters = null
    ) {
        // Concrete properties are explicitly declared and normalization is required in the constructor before assignment.
        // Short/invalid sorts map to null.
        // UTF-8 lengths are strictly verified using /./us regex.
    }

    public function jsonSerialize(): mixed
    {
        return [
            'page' => $this->page,
            'perPage' => $this->perPage,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'id' => $this->id,
            'eventId' => $this->eventId,
            'channel' => $this->channel,
            'operationType' => $this->operationType,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'status' => $this->status,
            'attemptNoMin' => $this->attemptNoMin,
            'attemptNoMax' => $this->attemptNoMax,
            'correlationId' => $this->correlationId,
            'requestId' => $this->requestId,
            'provider' => $this->provider,
            'providerMessageId' => $this->providerMessageId,
            'errorCode' => $this->errorCode,
            'errorMessageLike' => $this->errorMessageLike,
            'metadataFilters' => $this->metadataFilters,
            'scheduledAfter' => $this->scheduledAfter?->format(\DATE_ATOM),
            'scheduledBefore' => $this->scheduledBefore?->format(\DATE_ATOM),
            'completedAfter' => $this->completedAfter?->format(\DATE_ATOM),
            'completedBefore' => $this->completedBefore?->format(\DATE_ATOM),
            'occurredAfter' => $this->occurredAfter?->format(\DATE_ATOM),
            'occurredBefore' => $this->occurredBefore?->format(\DATE_ATOM),
            'nullStateFilters' => $this->nullStateFilters,
        ];
    }
}
```

### 3.4 Request Validation and Semantics
- **Length Limits**: eventId (36), channel (32), operationType (64), actorType (32), targetType (64), status (32), correlationId (36), requestId (64), provider (64), providerMessageId (128), errorCode (64), errorMessageLike (128 - safe upper bound for substring search).
- **Independence**: `actorType`/`actorId` and `targetType`/`targetId` are independently filterable. Positive integers for IDs.
- **Ranges**: `scheduledAfter` <= `scheduledBefore`, `completedAfter` <= `completedBefore`, `occurredAfter` <= `occurredBefore`.
- **Pagination Limits**: `page`/`perPage` defaults are passed as `null` to `maatify/persistence` to apply normalization (perPage default 20, clamp 1-200).
- **Retry Bounds**: `attemptNoMin` and `attemptNoMax` must be unsigned (>= 0). If both are set, `attemptNoMin <= attemptNoMax`.
- **Text Search**: `errorMessageLike` uses safe exact contains search with escaping (`LIKE :error_message_like ESCAPE '\\'`). Escaping must natively escape `\`, `%`, and `_`. Case insensitivity depends on the table's `utf8mb4_unicode_ci` collation.
- **Metadata JSON Search**: `metadataFilters` must be an exact associative array mapping `['stringPath' => 'stringEqualityValue']`. No arbitrary JSON path evaluation. Maximum 5 filters per request. The path format must exactly be alpha-numeric strings or dot-notation matching MySQL JSON extraction (e.g. `$.key`).
- **Null-State Tri-State**: `nullStateFilters` accepts an associative array mapping column property names (`actorType`, `targetId`, `scheduledAt`, `completedAt`, `correlationId`, `requestId`, `provider`, `providerMessageId`, `errorCode`, `errorMessage`) to a strict boolean `true` (`IS NULL`) or `false` (`IS NOT NULL`).

### 3.5 Result DTO
`DeliveryOperationsAdminPageResultDTO`
Must match the exact structure of other domains.
```php
namespace Maatify\EventLogging\DeliveryOperations\DTO;

use Traversable;

final readonly class DeliveryOperationsAdminPageResultDTO implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @param list<DeliveryOperationsViewDTO> $items
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

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }

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

## 4. Internal Mechanics and SQL

### 4.1 SQL Columns
Explicit selection without `SELECT *`:
`id, event_id, channel, operation_type, actor_type, actor_id, target_type, target_id, status, attempt_no, scheduled_at, completed_at, correlation_id, request_id, provider, provider_message_id, error_code, error_message, metadata, occurred_at`

### 4.2 SQL Clauses and Placeholders
The Descriptor Builder (`DeliveryOperationsAdminQueryDescriptorBuilder`) generates:
- `whereSql`: Starts with ` WHERE ` and uses ` AND ` for conditions. No trailing characters if empty.
- Parameters must use exact unique named placeholders (e.g., `:event_id`, `:actor_type`, `:completed_before`, `:attempt_no_min`, `:attempt_no_max`).
- `metadata` filter uses exact SQL/JSON safe extraction for the specific engine supported (MySQL). `JSON_UNQUOTE(JSON_EXTRACT(metadata, :metadata_path_1)) = :metadata_value_1`.
- `totalSql` and `filteredCountSql` must share the exact same `whereSql` source as `dataSql`.
- No `ORDER BY`, `LIMIT`, or `OFFSET` in `dataSql`.
- UTC formatting `Y-m-d H:i:s.u` for timestamps.

### 4.3 Repository Construction
`DeliveryOperationsAdminQueryMysqlRepository` is `public final`.
```php
namespace Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql;

final class DeliveryOperationsAdminQueryMysqlRepository implements DeliveryOperationsAdminQueryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
        // Instantiates PdoPaginator, DeliveryOperationsRowMapper, and Descriptor Builder internally.
    }
}
```

### 4.4 Shared Mapper
`DeliveryOperationsRowMapper` is `/** @internal */ final`.
It safely handles JSON metadata string decoding and fallback values. It does not apply policy normalization, as the primitive query hydrate channel/operation/status as raw strings.
Exact behavior for mapping:
- numeric string to int fallback.
- invalid string to null/empty values.
- metadata decoding failure or scalar shape to `null`.

## 5. Verification Matrix (Test Design)

### 5.1 Unit Tests
- `DeliveryOperationsAdminQueryRequestDTOTest`: Every normalization and validation rule (UTF-8 length, invalid dates, sorting logic).
- `DeliveryOperationsAdminPageResultDTOTest`: Exact serialization order.
- `DeliveryOperationsAdminQueryDescriptorBuilderTest`: Descriptor generation (explicit columns, exactly correct placeholders, no offset/limit, metadata JSON search, null-state generation, timestamp ranges, LIKE escaping).
- `DeliveryOperationsRowMapperTest`: JSON fallback, types, null handling, missing array keys.
- `DeliveryOperationsAdminQueryMysqlRepositoryTest`: Exception translation and delegation assertions.
- `DeliveryOperationsAdminQueryExceptionTest`: Formatting and previous preservation.

### 5.2 Regression Tests
- `DeliveryOperationsQueryMysqlRepositoryRegressionTest`: Prove primitive `find()`, limits, exception boundaries, constructor preserved, cursor behavior, strict policy-free mapping preserved.

### 5.3 Strict MySQL Integration Tests
- `DeliveryOperationsAdminQueryMysqlRepositoryTest`: Strict database test.
- Missing/empty `EVENT_LOGGING_TEST_MYSQL_DSN` -> throws `RuntimeException`. No `markTestSkipped()`.
- Must execute against native MySQL using `PDO::ATTR_EMULATE_PREPARES => false`, `PDO::FETCH_ASSOC`, and `PDO::ERRMODE_EXCEPTION`.
- Every independent filter, range boundaries, complex combinations.
- Metadata JSON exact filtering.
- Null-state `true`/`false` exact isolation.
- Pagination navigation, overflow, deterministic tie-breaking (`id DESC`).
- Failure mapping (e.g., forced schema/table missing or invalid SQL context for `PDOException`).
- Caller-owned transaction preservation (transaction state unchanged after read).
