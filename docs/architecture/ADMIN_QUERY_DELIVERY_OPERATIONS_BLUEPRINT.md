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
The newly exposed unindexed filters (`target_id`, `provider`, `error_code`, etc.) have scan implications. They are intentionally exposed to provide complete Admin filtering, leaving index optimization for a separate future migration if proven necessary.

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
#### `DeliveryOperationsAdminQueryInvalidArgumentException`
Extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException`, implements `EventLoggingExceptionInterface`. Inherits parent's default error code.
```php
    public static function invalidId(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query ID: {$field}");
    }
    public static function invalidRetryRange(): self
    {
        return new self("Invalid DeliveryOperations Admin Query retry range: attempt_no_min must be less than or equal to attempt_no_max");
    }
    public static function invalidRetryValue(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query retry value: {$field}");
    }
    public static function invalidLength(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query length: {$field}");
    }
    public static function invalidEncoding(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query UTF-8 encoding: {$field}");
    }
    public static function invalidDateRange(string $rangeName): self
    {
        return new self("Invalid DeliveryOperations Admin Query date range: {$rangeName} after must be before or equal to before");
    }
    public static function invalidNullState(string $field): self
    {
        return new self("Invalid DeliveryOperations Admin Query null-state input: {$field}");
    }
    public static function invalidErrorMessageSearch(): self
    {
        return new self("Invalid DeliveryOperations Admin Query error_message search input");
    }
    public static function invalidMetadataPath(): self
    {
        return new self("Invalid DeliveryOperations Admin Query metadata path or shape");
    }
```

#### `DeliveryOperationsAdminQueryExecutionException`
Extends `Maatify\Exceptions\Exception\System\SystemMaatifyException`, implements `EventLoggingExceptionInterface`. Uses `ErrorCodeEnum::MAATIFY_ERROR`.
```php
    public static function executionFailed(\Throwable $previous): self
    {
        return new self("DeliveryOperations Admin Query execution failed: " . $previous->getMessage(), previous: $previous);
    }
```

### 3.3 Request DTO
`DeliveryOperationsAdminQueryRequestDTO`
Constructed with all valid filter dimensions. Strings must be trimmed and UTF-8 validated. Short invalid sort values map to `null`.
```php
namespace Maatify\EventLogging\DeliveryOperations\DTO;

use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;

final class DeliveryOperationsAdminQueryRequestDTO implements \JsonSerializable
{
    public readonly int|string|null $page;
    public readonly int|string|null $perPage;
    public readonly ?string $sortBy;
    public readonly ?string $sortDirection;
    public readonly ?int $id;
    public readonly ?string $eventId;
    public readonly ?string $channel;
    public readonly ?string $operationType;
    public readonly ?string $actorType;
    public readonly ?int $actorId;
    public readonly ?string $targetType;
    public readonly ?int $targetId;
    public readonly ?string $status;
    public readonly ?int $attemptNoMin;
    public readonly ?int $attemptNoMax;
    public readonly ?string $correlationId;
    public readonly ?string $requestId;
    public readonly ?string $provider;
    public readonly ?string $providerMessageId;
    public readonly ?string $errorCode;
    public readonly ?string $errorMessageLike;
    public readonly ?array $metadataFilters;
    public readonly ?\DateTimeImmutable $scheduledAfter;
    public readonly ?\DateTimeImmutable $scheduledBefore;
    public readonly ?\DateTimeImmutable $completedAfter;
    public readonly ?\DateTimeImmutable $completedBefore;
    public readonly ?\DateTimeImmutable $after;
    public readonly ?\DateTimeImmutable $before;
    public readonly ?array $nullStateFilters;

    public function __construct(
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
        ?int $id = null,
        ?string $eventId = null,
        ?string $channel = null,
        ?string $operationType = null,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $status = null,
        ?int $attemptNoMin = null,
        ?int $attemptNoMax = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $provider = null,
        ?string $providerMessageId = null,
        ?string $errorCode = null,
        ?string $errorMessageLike = null,
        ?array $metadataFilters = null,
        ?\DateTimeImmutable $scheduledAfter = null,
        ?\DateTimeImmutable $scheduledBefore = null,
        ?\DateTimeImmutable $completedAfter = null,
        ?\DateTimeImmutable $completedBefore = null,
        ?\DateTimeImmutable $after = null,
        ?\DateTimeImmutable $before = null,
        ?array $nullStateFilters = null
    ) {
        $this->page = $page;
        $this->perPage = $perPage;

        // Example normalizations explicitly enforced:
        $this->sortBy = (is_string($sortBy) && mb_strlen($sortBy) > 64) ? null : $sortBy;
        $this->sortDirection = (is_string($sortDirection) && mb_strlen($sortDirection) > 4) ? null : $sortDirection;

        $this->id = $id;
        if ($this->id !== null && $this->id <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('id');
        }

        $this->eventId = is_string($eventId) ? trim($eventId) : null;
        if ($this->eventId === '') $this->eventId = null;
        if ($this->eventId !== null && !preg_match('/./us', $this->eventId)) {
             throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding('eventId');
        }
        if ($this->eventId !== null && preg_match_all('/./us', $this->eventId) > 36) {
             throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength('eventId');
        }

        $this->channel = is_string($channel) ? trim($channel) : null;
        if ($this->channel === '') $this->channel = null;
        // Validation length 32

        $this->operationType = is_string($operationType) ? trim($operationType) : null;
        if ($this->operationType === '') $this->operationType = null;
        // Validation length 64

        $this->actorType = is_string($actorType) ? trim($actorType) : null;
        if ($this->actorType === '') $this->actorType = null;
        // Validation length 32

        $this->actorId = $actorId;
        if ($this->actorId !== null && $this->actorId <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('actorId');
        }

        $this->targetType = is_string($targetType) ? trim($targetType) : null;
        if ($this->targetType === '') $this->targetType = null;
        // Validation length 64

        $this->targetId = $targetId;
        if ($this->targetId !== null && $this->targetId <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('targetId');
        }

        $this->status = is_string($status) ? trim($status) : null;
        if ($this->status === '') $this->status = null;
        // Validation length 32

        $this->attemptNoMin = $attemptNoMin;
        if ($this->attemptNoMin !== null && $this->attemptNoMin < 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryValue('attemptNoMin');
        }
        $this->attemptNoMax = $attemptNoMax;
        if ($this->attemptNoMax !== null && $this->attemptNoMax < 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryValue('attemptNoMax');
        }
        if ($this->attemptNoMin !== null && $this->attemptNoMax !== null && $this->attemptNoMin > $this->attemptNoMax) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryRange();
        }

        $this->correlationId = is_string($correlationId) ? trim($correlationId) : null;
        if ($this->correlationId === '') $this->correlationId = null;
        // Validation length 36

        $this->requestId = is_string($requestId) ? trim($requestId) : null;
        if ($this->requestId === '') $this->requestId = null;
        // Validation length 64

        $this->provider = is_string($provider) ? trim($provider) : null;
        if ($this->provider === '') $this->provider = null;
        // Validation length 64

        $this->providerMessageId = is_string($providerMessageId) ? trim($providerMessageId) : null;
        if ($this->providerMessageId === '') $this->providerMessageId = null;
        // Validation length 128

        $this->errorCode = is_string($errorCode) ? trim($errorCode) : null;
        if ($this->errorCode === '') $this->errorCode = null;
        // Validation length 64

        $this->errorMessageLike = is_string($errorMessageLike) ? trim($errorMessageLike) : null;
        if ($this->errorMessageLike === '') $this->errorMessageLike = null;
        // Validation length 128 safe upper bound
        if ($this->errorMessageLike !== null && !preg_match('/./us', $this->errorMessageLike)) {
             throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding('errorMessageLike');
        }
        if ($this->errorMessageLike !== null && preg_match_all('/./us', $this->errorMessageLike) > 128) {
             throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength('errorMessageLike');
        }

        $this->metadataFilters = $metadataFilters;
        // Explicit metadata validation: max 5, valid keys, valid scalars (string|int|float|bool|null).

        $this->scheduledAfter = $scheduledAfter;
        $this->scheduledBefore = $scheduledBefore;
        if ($this->scheduledAfter && $this->scheduledBefore && $this->scheduledAfter > $this->scheduledBefore) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('scheduled_at');
        }

        $this->completedAfter = $completedAfter;
        $this->completedBefore = $completedBefore;
        if ($this->completedAfter && $this->completedBefore && $this->completedAfter > $this->completedBefore) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('completed_at');
        }

        $this->after = $after;
        $this->before = $before;
        if ($this->after && $this->before && $this->after > $this->before) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('occurred_at');
        }

        $this->nullStateFilters = $nullStateFilters;
        // Explicit null state validation: strict bools for exact column whitelist.
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
            'after' => $this->after?->format(\DATE_ATOM),
            'before' => $this->before?->format(\DATE_ATOM),
            'nullStateFilters' => $this->nullStateFilters,
        ];
    }
}
```

### 3.4 Request Validation and Semantics
- **Length Limits**: eventId (36), channel (32), operationType (64), actorType (32), targetType (64), status (32), correlationId (36), requestId (64), provider (64), providerMessageId (128), errorCode (64), errorMessageLike (128). Strings are trimmed and UTF-8 validated `preg_match_all('/./us', $val)`.
- **Independence**: `actorType`/`actorId` and `targetType`/`targetId` are independently filterable. Positive integers for IDs.
- **Ranges**: `scheduledAfter` <= `scheduledBefore`, `completedAfter` <= `completedBefore`, `after` <= `before`.
- **Pagination Limits**: `page`/`perPage` defaults are passed as `null` to `maatify/persistence` to apply normalization.
- **Retry Bounds**: `attemptNoMin` and `attemptNoMax` must be unsigned (>= 0). If both are set, `attemptNoMin <= attemptNoMax`.
- **Text Search**: `errorMessageLike` uses safe exact contains search with escaping (`LIKE :error_message_like ESCAPE '\\'`). Escaping must natively escape `\`, `%`, and `_`. Case insensitivity depends on the table's collation.
- **Null-State Tri-State**: `nullStateFilters` accepts an associative array mapping exactly to these allowed property names: `actorType`, `actorId`, `targetType`, `targetId`, `scheduledAt`, `completedAt`, `correlationId`, `requestId`, `provider`, `providerMessageId`, `errorCode`, `errorMessage`. Values must be strictly `bool` (`true` -> `IS NULL`, `false` -> `IS NOT NULL`). An exception is thrown for invalid properties or non-bool values. Duplicate semantic conditions (e.g., both equality and null-state) naturally translate into SQL `WHERE column = X AND column IS NULL`, producing 0 rows as intended.
- **Metadata JSON Search**: `metadataFilters` must be an exact associative array mapping `['stringPath' => scalarValue]`. No arbitrary JSON path evaluation. Maximum 5 filters per request. The path format must explicitly start with `$.` followed by alpha-numeric strings or valid unquoted JSON keys (nesting allowed via `.` up to depth 5, max total length 64). Allowed scalar value types: `string|int|float|bool|null`. Values are natively JSON-encoded for bound parameters. A JSON `null` filter explicitly searches for `CAST(NULL AS JSON)` value stored in the document, whereas missing a path returns NULL which requires `JSON_CONTAINS_PATH(metadata, 'one', :path) = 1` checks to differentiate. Null vs missing must be handled correctly in SQL.

### 3.5 Result DTO
`DeliveryOperationsAdminPageResultDTO`
Must match the exact structure of other domains.
```php
namespace Maatify\EventLogging\DeliveryOperations\DTO;

use Traversable;

/**
 * @implements \IteratorAggregate<int, DeliveryOperationsViewDTO>
 */
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

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return array{
     *     items: list<DeliveryOperationsViewDTO>,
     *     page: int,
     *     perPage: int,
     *     total: int,
     *     filtered: int,
     *     totalPages: int,
     *     hasNext: bool,
     *     hasPrevious: bool,
     *     sortBy: string,
     *     sortDirection: string
     * }
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

## 4. Complete Filter SQL Contract

| Field | PHP Property | PHP Type | Normalization/Validation | SQL Condition | Placeholders | Null Behavior |
| --- | --- | --- | --- | --- | --- | --- |
| `id` | `id` | `?int` | Must be > 0 | `id = :id` | `:id` | N/A |
| `event_id` | `eventId` | `?string` | length <= 36 | `event_id = :event_id` | `:event_id` | N/A |
| `channel` | `channel` | `?string` | length <= 32 | `channel = :channel` | `:channel` | N/A |
| `operation_type` | `operationType` | `?string` | length <= 64 | `operation_type = :operation_type` | `:operation_type` | N/A |
| `actor_type` | `actorType` | `?string` | length <= 32 | `actor_type = :actor_type` | `:actor_type` | Null-state explicit |
| `actor_id` | `actorId` | `?int` | Must be > 0 | `actor_id = :actor_id` | `:actor_id` | Null-state explicit |
| `target_type` | `targetType` | `?string` | length <= 64 | `target_type = :target_type` | `:target_type` | Null-state explicit |
| `target_id` | `targetId` | `?int` | Must be > 0 | `target_id = :target_id` | `:target_id` | Null-state explicit |
| `status` | `status` | `?string` | length <= 32 | `status = :status` | `:status` | N/A |
| `attempt_no` | `attemptNoMin`, `attemptNoMax` | `?int` | `>= 0`, min <= max | `attempt_no >= :attempt_no_min AND attempt_no <= :attempt_no_max` | `:attempt_no_min`, `:attempt_no_max` | N/A |
| `correlation_id` | `correlationId` | `?string` | length <= 36 | `correlation_id = :correlation_id` | `:correlation_id` | Null-state explicit |
| `request_id` | `requestId` | `?string` | length <= 64 | `request_id = :request_id` | `:request_id` | Null-state explicit |
| `provider` | `provider` | `?string` | length <= 64 | `provider = :provider` | `:provider` | Null-state explicit |
| `provider_message_id` | `providerMessageId` | `?string` | length <= 128 | `provider_message_id = :provider_message_id` | `:provider_message_id` | Null-state explicit |
| `error_code` | `errorCode` | `?string` | length <= 64 | `error_code = :error_code` | `:error_code` | Null-state explicit |
| `error_message` | `errorMessageLike` | `?string` | length <= 128 | `error_message LIKE :error_message_like ESCAPE '\\'` | `:error_message_like` | Null-state explicit |
| `scheduled_at` | `scheduledAfter`, `scheduledBefore` | `?\DateTimeImmutable` | ranges | `scheduled_at >= :scheduled_after AND scheduled_at <= :scheduled_before` | `:scheduled_after`, `:scheduled_before` | Null-state explicit |
| `completed_at` | `completedAfter`, `completedBefore` | `?\DateTimeImmutable` | ranges | `completed_at >= :completed_after AND completed_at <= :completed_before` | `:completed_after`, `:completed_before` | Null-state explicit |
| `occurred_at` | `after`, `before` | `?\DateTimeImmutable` | ranges | `occurred_at >= :after AND occurred_at <= :before` | `:after`, `:before` | N/A |
| `metadata` | `metadataFilters` | `?array` | Max 5, JSON scalars | `JSON_CONTAINS_PATH(metadata, 'one', :meta_path_1) = 1 AND JSON_EXTRACT(metadata, :meta_path_1) = CAST(:meta_val_1 AS JSON)` | `:meta_path_1`, `:meta_val_1` | N/A |

**Total SQL:**
`SELECT COUNT(*) FROM maa_event_logging_delivery_operations`
**Filtered Count SQL:**
`SELECT COUNT(*) FROM maa_event_logging_delivery_operations WHERE <conditions>`
**Data SQL:**
`SELECT id, event_id, channel, operation_type, actor_type, actor_id, target_type, target_id, status, attempt_no, scheduled_at, completed_at, correlation_id, request_id, provider, provider_message_id, error_code, error_message, metadata, occurred_at FROM maa_event_logging_delivery_operations WHERE <conditions>`

- Filtered count and data SQL append the exact same `whereSql`.
- No `ORDER BY`, `LIMIT`, or `OFFSET` in `dataSql`.
- UTC formatting `Y-m-d H:i:s.u`.

## 5. Repository and Pagination Mechanics

### 5.1 Repository Construction
`DeliveryOperationsAdminQueryMysqlRepository` is `public final`.
```php
namespace Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql;

final class DeliveryOperationsAdminQueryMysqlRepository implements DeliveryOperationsAdminQueryInterface
{
    private \Maatify\Persistence\Pdo\Pagination\PdoPaginator $paginator;
    private DeliveryOperationsRowMapper $mapper;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->paginator = new \Maatify\Persistence\Pdo\Pagination\PdoPaginator();
        $this->mapper = new DeliveryOperationsRowMapper();
    }

    public function paginate(DeliveryOperationsAdminQueryRequestDTO $request): DeliveryOperationsAdminPageResultDTO
    {
        // 1. Convert DTO -> PaginationConfig (occurred_at only sort, id tiebreaker, 20 default, min 1, max 200).
        // 2. Build PageRequest.
        // 3. Build PdoPaginationQueryDescriptor.
        // 4. Call $this->paginator->paginate($this->pdo, ...).
        // 5. Catch PaginationExecutionException|PDOException -> DeliveryOperationsStorageException.
        // 6. Catch InvalidPaginationConfigurationException|InvalidPaginationQueryException -> DeliveryOperationsAdminQueryExecutionException.
        // 7. Adapt PdoPageResult to DeliveryOperationsAdminPageResultDTO.
    }
}
```

### 5.2 Exception Mapping
- `PaginationExecutionException|PDOException` -> `DeliveryOperationsStorageException` with `Failed to query DeliveryOperations records: {message}` + previous throwable.
- `InvalidPaginationConfigurationException|InvalidPaginationQueryException` -> `DeliveryOperationsAdminQueryExecutionException::executionFailed()`.
- Mapper failures -> `DeliveryOperationsStorageException` with `Failed to map DeliveryOperations row: {message}` + previous throwable.
- Already-created `DeliveryOperationsStorageException` must propagate without double-wrapping.
- No `BEGIN/COMMIT/ROLLBACK` statements exist here.

### 5.3 Shared Mapper
`DeliveryOperationsRowMapper` is `/** @internal */ final`.
Exact fallback behavior preserving primitive query:
- `id`: numeric -> int, otherwise 0
- `event_id/channel/operation_type/status`: string -> value, otherwise ''
- `actor_type/target_type/correlation_id/request_id/provider/provider_message_id/error_code/error_message`: string -> value, otherwise null
- `actor_id/target_id/attempt_no`: numeric -> int, otherwise null
- `scheduled_at/completed_at`: valid date string -> DateTimeImmutable, otherwise null
- `occurred_at`: valid date string -> DateTimeImmutable, otherwise epoch. Invalid format throws mapper exception.
- `metadata`: non-empty string decoded to any array -> array; missing/non-string/empty/malformed/scalar/associative-object/numeric-key-array -> handled by JSON decode. Missing or invalid shape resolves to `null`.
No policy is injected into this read mapper.

## 6. Verification Matrix (Test Design)

### 6.1 Unit Tests
- `DeliveryOperationsAdminQueryRequestDTOTest`: Every normalization and validation rule (UTF-8 length, invalid dates, sorting logic, null-state filters).
- `DeliveryOperationsAdminPageResultDTOTest`: Exact serialization order.
- `DeliveryOperationsAdminQueryDescriptorBuilderTest`: Descriptor generation (explicit columns, exactly correct placeholders, no offset/limit, metadata JSON search, null-state generation, timestamp ranges, LIKE escaping).
- `DeliveryOperationsRowMapperTest`: JSON fallback, types, null handling, missing array keys.
- `DeliveryOperationsAdminQueryMysqlRepositoryTest`: Exception translation and delegation assertions.
- `DeliveryOperationsAdminQueryExceptionTest`: Formatting and previous preservation.

### 6.2 Regression Tests
- `DeliveryOperationsQueryMysqlRepositoryRegressionTest`: Prove primitive `find()`, limits, exception boundaries, constructor preserved, cursor behavior, strict policy-free mapping preserved.
- Full primitive contract regression protecting writer, recorder, policy, bindings, fail-open boundary.

### 6.3 Strict MySQL Integration Tests
- `DeliveryOperationsAdminQueryMysqlRepositoryTest`: Strict database test.
- Missing/empty `EVENT_LOGGING_TEST_MYSQL_DSN` -> throws `RuntimeException`. No `markTestSkipped()`.
- Must execute against native MySQL using `PDO::ATTR_EMULATE_PREPARES => false`, `PDO::FETCH_ASSOC`, and `PDO::ERRMODE_EXCEPTION`.
- Every independent filter, range boundaries, complex combinations.
- Metadata JSON exact filtering.
- Null-state `true`/`false` exact isolation.
- Pagination navigation, overflow, deterministic tie-breaking (`id DESC`).
- Failure mapping (e.g., forced schema/table missing or invalid SQL context for `PDOException`).
- Caller-owned transaction preservation (transaction state unchanged after read).
- Unindexed-filter scan implications explicitly accepted in tests without skipping them.

## 7. Later Runtime sequence
- public contracts, DTO validation/serialization, and exceptions;
- policy-free mapper and descriptor builder;
- Admin MySQL repository and Unit exception/execution gates;
- strict native-MySQL Admin Integration gates;
- final package/integration/domain documentation and full verification.
