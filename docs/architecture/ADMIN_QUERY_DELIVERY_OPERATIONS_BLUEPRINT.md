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

No primitive corrections are authorized in this PR.
The newly exposed unindexed filters (`target_id`, `provider`, `error_code`, etc.) have scan implications. They are intentionally exposed to provide complete Admin filtering, leaving index optimization for a separate future migration if proven necessary. Silently removing them is forbidden.

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
    public static function invalidMetadataCount(): self
    {
        return new self("Invalid DeliveryOperations Admin Query metadata filter count");
    }
    public static function invalidMetadataPath(): self
    {
        return new self("Invalid DeliveryOperations Admin Query metadata path or shape");
    }
    public static function invalidMetadataValue(): self
    {
        return new self("Invalid DeliveryOperations Admin Query metadata value type");
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
    /** @var array<string, string|int|float|bool|null>|null */
    public readonly ?array $metadataFilters;
    public readonly ?\DateTimeImmutable $scheduledAfter;
    public readonly ?\DateTimeImmutable $scheduledBefore;
    public readonly ?\DateTimeImmutable $completedAfter;
    public readonly ?\DateTimeImmutable $completedBefore;
    public readonly ?\DateTimeImmutable $after;
    public readonly ?\DateTimeImmutable $before;
    /** @var array<string, bool>|null */
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

        $normSortBy = is_string($sortBy) ? trim($sortBy) : null;
        if ($normSortBy === '') $normSortBy = null;
        if ($normSortBy !== null && !preg_match('/./us', $normSortBy)) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding('sortBy');
        }
        if ($normSortBy !== null && preg_match_all('/./us', $normSortBy) > 64) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength('sortBy');
        }
        $this->sortBy = $normSortBy === 'occurred_at' ? 'occurred_at' : null;

        $normSortDir = is_string($sortDirection) ? trim($sortDirection) : null;
        if ($normSortDir === '') $normSortDir = null;
        if ($normSortDir !== null && !preg_match('/./us', $normSortDir)) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding('sortDirection');
        }
        if ($normSortDir !== null && preg_match_all('/./us', $normSortDir) > 4) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength('sortDirection');
        }
        if ($normSortDir !== null) {
            $normSortDir = strtoupper($normSortDir);
            $normSortDir = in_array($normSortDir, ['ASC', 'DESC'], true) ? $normSortDir : null;
        }
        $this->sortDirection = $normSortDir;

        if ($id !== null && $id <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('id');
        }
        $this->id = $id;

        $this->eventId = self::normalizeString($eventId, 'eventId', 36);
        $this->channel = self::normalizeString($channel, 'channel', 32);
        $this->operationType = self::normalizeString($operationType, 'operationType', 64);
        $this->actorType = self::normalizeString($actorType, 'actorType', 32);

        if ($actorId !== null && $actorId <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('actorId');
        }
        $this->actorId = $actorId;

        $this->targetType = self::normalizeString($targetType, 'targetType', 64);

        if ($targetId !== null && $targetId <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('targetId');
        }
        $this->targetId = $targetId;

        $this->status = self::normalizeString($status, 'status', 32);

        if ($attemptNoMin !== null && $attemptNoMin < 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryValue('attemptNoMin');
        }
        if ($attemptNoMax !== null && $attemptNoMax < 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryValue('attemptNoMax');
        }
        if ($attemptNoMin !== null && $attemptNoMax !== null && $attemptNoMin > $attemptNoMax) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryRange();
        }
        $this->attemptNoMin = $attemptNoMin;
        $this->attemptNoMax = $attemptNoMax;

        $this->correlationId = self::normalizeString($correlationId, 'correlationId', 36);
        $this->requestId = self::normalizeString($requestId, 'requestId', 64);
        $this->provider = self::normalizeString($provider, 'provider', 64);
        $this->providerMessageId = self::normalizeString($providerMessageId, 'providerMessageId', 128);
        $this->errorCode = self::normalizeString($errorCode, 'errorCode', 64);
        $this->errorMessageLike = self::normalizeString($errorMessageLike, 'errorMessageLike', 128);

        if ($metadataFilters !== null) {
            if (count($metadataFilters) > 5) {
                throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataCount();
            }
            foreach ($metadataFilters as $path => $value) {
                if (!is_string($path) || mb_strlen($path) > 64 || !preg_match('/^\$\.[A-Za-z0-9_]+(\.[A-Za-z0-9_]+){0,4}$/', $path)) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataPath();
                }
                if ($value !== null && !is_scalar($value)) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataValue();
                }
                try {
                    json_encode($value, \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataValue();
                }
            }
        }
        $this->metadataFilters = $metadataFilters;

        if ($scheduledAfter && $scheduledBefore && $scheduledAfter > $scheduledBefore) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('scheduled_at');
        }
        $this->scheduledAfter = $scheduledAfter;
        $this->scheduledBefore = $scheduledBefore;

        if ($completedAfter && $completedBefore && $completedAfter > $completedBefore) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('completed_at');
        }
        $this->completedAfter = $completedAfter;
        $this->completedBefore = $completedBefore;

        if ($after && $before && $after > $before) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('occurred_at');
        }
        $this->after = $after;
        $this->before = $before;

        if ($nullStateFilters !== null) {
            $allowed = [
                'actorType', 'actorId', 'targetType', 'targetId',
                'scheduledAt', 'completedAt', 'correlationId', 'requestId',
                'provider', 'providerMessageId', 'errorCode', 'errorMessage'
            ];
            foreach ($nullStateFilters as $key => $val) {
                if (!in_array($key, $allowed, true) || !is_bool($val)) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidNullState($key);
                }
            }
        }
        $this->nullStateFilters = $nullStateFilters;
    }

    private static function normalizeString(?string $value, string $field, int $maxLength): ?string
    {
        $val = is_string($value) ? trim($value) : null;
        if ($val === '') return null;
        if ($val !== null && !preg_match('/./us', $val)) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding($field);
        }
        if ($val !== null && preg_match_all('/./us', $val) > $maxLength) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength($field);
        }
        return $val;
    }

    public function jsonSerialize(): array
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
- **Metadata JSON Search**: `metadataFilters` must be an exact associative array mapping `['stringPath' => scalarValue]`. No arbitrary JSON path evaluation. Maximum 5 filters per request. The path format must strictly enforce leading `$.`, exactly 1 to 5 non-empty ASCII `[A-Za-z0-9_]+` segments, dot separators only, no repeated dots, and maximum total length 64. Allowed scalar value types: `string|int|float|bool|null`. Values must be successfully encoded via `json_encode(..., JSON_THROW_ON_ERROR)`. A JSON `null` filter explicitly searches for `CAST(NULL AS JSON)` value stored in the document, whereas missing a path returns NULL which requires `JSON_CONTAINS_PATH(metadata, 'one', :path) = 1` checks to differentiate. Null vs missing must be handled correctly in SQL.

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

| Field | PHP Property | PHP Type | Normalization/Validation | SQL Condition | Placeholders | Parameter Type / Encoding | Null Behavior |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `id` | `id` | `?int` | Must be > 0 | `id = :id` | `id` | `int` | N/A |
| `event_id` | `eventId` | `?string` | length <= 36 | `event_id = :event_id` | `event_id` | `string` | N/A |
| `channel` | `channel` | `?string` | length <= 32 | `channel = :channel` | `channel` | `string` | N/A |
| `operation_type` | `operationType` | `?string` | length <= 64 | `operation_type = :operation_type` | `operation_type` | `string` | N/A |
| `actor_type` | `actorType` | `?string` | length <= 32 | `actor_type = :actor_type` | `actor_type` | `string` | Null-state explicit |
| `actor_id` | `actorId` | `?int` | Must be > 0 | `actor_id = :actor_id` | `actor_id` | `int` | Null-state explicit |
| `target_type` | `targetType` | `?string` | length <= 64 | `target_type = :target_type` | `target_type` | `string` | Null-state explicit |
| `target_id` | `targetId` | `?int` | Must be > 0 | `target_id = :target_id` | `target_id` | `int` | Null-state explicit |
| `status` | `status` | `?string` | length <= 32 | `status = :status` | `status` | `string` | N/A |
| `attempt_no` | `attemptNoMin` | `?int` | `>= 0` | `attempt_no >= :attempt_no_min` | `attempt_no_min` | `int` | N/A |
| `attempt_no` | `attemptNoMax` | `?int` | `>= 0` | `attempt_no <= :attempt_no_max` | `attempt_no_max` | `int` | N/A |
| `correlation_id` | `correlationId` | `?string` | length <= 36 | `correlation_id = :correlation_id` | `correlation_id` | `string` | Null-state explicit |
| `request_id` | `requestId` | `?string` | length <= 64 | `request_id = :request_id` | `request_id` | `string` | Null-state explicit |
| `provider` | `provider` | `?string` | length <= 64 | `provider = :provider` | `provider` | `string` | Null-state explicit |
| `provider_message_id` | `providerMessageId` | `?string` | length <= 128 | `provider_message_id = :provider_message_id` | `provider_message_id` | `string` | Null-state explicit |
| `error_code` | `errorCode` | `?string` | length <= 64 | `error_code = :error_code` | `error_code` | `string` | Null-state explicit |
| `error_message` | `errorMessageLike` | `?string` | length <= 128 | `error_message LIKE :error_message_like ESCAPE '\\'` | `error_message_like` | `%` prepended/appended string | Null-state explicit |
| `scheduled_at` | `scheduledAfter` | `?\DateTimeImmutable` | ranges | `scheduled_at >= :scheduled_after` | `scheduled_after` | UTC `Y-m-d H:i:s.u` | Null-state explicit |
| `scheduled_at` | `scheduledBefore` | `?\DateTimeImmutable` | ranges | `scheduled_at <= :scheduled_before` | `scheduled_before` | UTC `Y-m-d H:i:s.u` | Null-state explicit |
| `completed_at` | `completedAfter` | `?\DateTimeImmutable` | ranges | `completed_at >= :completed_after` | `completed_after` | UTC `Y-m-d H:i:s.u` | Null-state explicit |
| `completed_at` | `completedBefore` | `?\DateTimeImmutable` | ranges | `completed_at <= :completed_before` | `completed_before` | UTC `Y-m-d H:i:s.u` | Null-state explicit |
| `occurred_at` | `after` | `?\DateTimeImmutable` | ranges | `occurred_at >= :after` | `after` | UTC `Y-m-d H:i:s.u` | N/A |
| `occurred_at` | `before` | `?\DateTimeImmutable` | ranges | `occurred_at <= :before` | `before` | UTC `Y-m-d H:i:s.u` | N/A |
| `metadata` | `metadataFilters` | `?array` | Max 5, JSON scalars | `JSON_CONTAINS_PATH(metadata, 'one', :meta_path_1) = 1 AND JSON_EXTRACT(metadata, :meta_path_1) = CAST(:meta_val_1 AS JSON)` | `meta_path_1`, `meta_val_1` | `string` (path), `json_encode(value, JSON_THROW_ON_ERROR)` | N/A |

### Null-State Filters
Property keys: `actorType`, `actorId`, `targetType`, `targetId`, `scheduledAt`, `completedAt`, `correlationId`, `requestId`, `provider`, `providerMessageId`, `errorCode`, `errorMessage`.
`true` generates `<column> IS NULL`.
`false` generates `<column> IS NOT NULL`.
Duplicate/conflict conditions (e.g. `actorId = 5` and `actorId => true` IS NULL) naturally map to `actor_id = 5 AND actor_id IS NULL`, safely producing 0 results.
No host-provided column names are permitted outside the exact property whitelist.

### Error Message Search
For `errorMessageLike`, the parameter value must be escaped using:
`'%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->errorMessageLike) . '%'`
This matches the explicit `ESCAPE '\\'` SQL clause.

### Metadata Paths
Path format strictly matches `/^\$\.[a-zA-Z0-9_\.]{1,61}$/us`. Callers must supply the leading `$.`. Maximum 5 keys. Deterministic placeholders (e.g., `meta_path_0`, `meta_val_0`). Parameter values encoded via `json_encode(..., JSON_THROW_ON_ERROR)`. JSON-null searches natively match `CAST('null' AS JSON)` if the path exists, enforced via `JSON_CONTAINS_PATH`.

### SQL Semantics
- **Empty filters:** generates empty `whereSql` (no `WHERE` string added to data/count SQL).
- **Parameters:** array keys strictly exclude leading colons (e.g., `['event_id' => '123']`).
- **totalSql:** `SELECT COUNT(*) FROM maa_event_logging_delivery_operations` (no `whereSql`, empty params)
- **filteredCountSql:** `SELECT COUNT(*) FROM maa_event_logging_delivery_operations` + `whereSql` + parameters
- **dataSql:** `SELECT id, event_id, channel, operation_type, actor_type, actor_id, target_type, target_id, status, attempt_no, scheduled_at, completed_at, correlation_id, request_id, provider, provider_message_id, error_code, error_message, metadata, occurred_at FROM maa_event_logging_delivery_operations` + `whereSql` + parameters
- No `ORDER BY`, `LIMIT`, or `OFFSET` in `dataSql`.

## 5. Repository and Pagination Mechanics

### 5.1 Repository Construction
`DeliveryOperationsAdminQueryMysqlRepository` is `public final`.
```php
namespace Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql;

use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsAdminQueryInterface;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminPageResultDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsViewDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryExecutionException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\Pagination\DeliveryOperationsAdminQueryDescriptorBuilder;
use Maatify\Persistence\Exception\InvalidPaginationConfigurationException;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Exception\PaginationExecutionException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;

final class DeliveryOperationsAdminQueryMysqlRepository implements DeliveryOperationsAdminQueryInterface
{
    private PdoPaginator $paginator;
    private DeliveryOperationsRowMapper $mapper;
    private DeliveryOperationsAdminQueryDescriptorBuilder $descriptorBuilder;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->paginator = new PdoPaginator();
        $this->mapper = new DeliveryOperationsRowMapper();
        $this->descriptorBuilder = new DeliveryOperationsAdminQueryDescriptorBuilder();
    }

    public function paginate(DeliveryOperationsAdminQueryRequestDTO $request): DeliveryOperationsAdminPageResultDTO
    {
        try {
            $config = new PaginationConfig(
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

            $pageRequest = new PageRequest(
                page: $request->page,
                perPage: $request->perPage,
                sortBy: $request->sortBy,
                sortDirection: $request->sortDirection
            );

            $descriptor = $this->descriptorBuilder->build($request);

            $pageResult = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $config,
                fn(array $row) => $this->mapRow($row)
            );

            return new DeliveryOperationsAdminPageResultDTO(
                $pageResult->data,
                $pageResult->page,
                $pageResult->perPage,
                $pageResult->total,
                $pageResult->filtered,
                $pageResult->totalPages,
                $pageResult->hasNext,
                $pageResult->hasPrevious,
                $pageResult->sortBy,
                $pageResult->sortDirection->value
            );
        } catch (InvalidPaginationConfigurationException|InvalidPaginationQueryException $e) {
            throw DeliveryOperationsAdminQueryExecutionException::executionFailed($e);
        } catch (PaginationExecutionException|\PDOException $e) {
            throw new DeliveryOperationsStorageException('Failed to query DeliveryOperations records: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return DeliveryOperationsViewDTO
     * @throws DeliveryOperationsStorageException
     */
    private function mapRow(array $row): DeliveryOperationsViewDTO
    {
        try {
            return $this->mapper->map($row);
        } catch (DeliveryOperationsStorageException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DeliveryOperationsStorageException('Failed to map DeliveryOperations row: ' . $e->getMessage(), previous: $e);
        }
    }
}
```

### 5.2 Exception Mapping Details
- Mapper failures are caught as `\Throwable` within the `mapRow` callback, wrapped as `DeliveryOperationsStorageException` with `Failed to map DeliveryOperations row:` prefix.
- `DeliveryOperationsStorageException` caught explicitly avoids double-wrapping (e.g. if the mapper natively throws it).
- PDO execution fails wrap into `Failed to query DeliveryOperations records:`.
- Invalid configuration routes to execution exception.
- No transaction state mutation (`BEGIN/COMMIT/ROLLBACK`).

### 5.3 Shared Mapper
`DeliveryOperationsRowMapper` is `/** @internal */ final`.
Exact fallback behavior preserving primitive query:
- `id`: numeric -> int, otherwise `0`
- `event_id`, `channel`, `operation_type`, `status`: string -> value, otherwise `''`
- `actor_type/target_type/correlation_id/request_id/provider/provider_message_id/error_code/error_message`: string -> value, otherwise `null`
- `actor_id/target_id`: numeric -> int, otherwise `null`
- `attempt_no`: numeric -> int, otherwise `0`
- `scheduled_at/completed_at`: valid date string -> parsed as UTC DateTimeImmutable, otherwise (null/missing/non-string) -> `null`. Invalid date text throws mapper exception.
- `occurred_at`: valid string -> parsed as UTC DateTimeImmutable; missing/non-string -> epoch UTC. Invalid date text throws mapper exception.
- `metadata`: associative arrays (incl empty) -> array; missing/non-string/empty string/malformed/scalar/numeric-key-array -> `null`.

## 6. Exact File and Test Inventory

### 6.1 Repository Files
- `src/DeliveryOperations/Contract/DeliveryOperationsAdminQueryInterface.php`
- `src/DeliveryOperations/DTO/DeliveryOperationsAdminPageResultDTO.php`
- `src/DeliveryOperations/DTO/DeliveryOperationsAdminQueryRequestDTO.php`
- `src/DeliveryOperations/Exception/DeliveryOperationsAdminQueryExecutionException.php`
- `src/DeliveryOperations/Exception/DeliveryOperationsAdminQueryInvalidArgumentException.php`
- `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsAdminQueryMysqlRepository.php`
- `src/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsRowMapper.php` (Internal)
- `src/DeliveryOperations/Infrastructure/Mysql/Pagination/DeliveryOperationsAdminQueryDescriptorBuilder.php` (Internal)

### 6.2 Tests
- `tests/Unit/DeliveryOperations/DTO/DeliveryOperationsAdminQueryRequestDTOTest.php`
- `tests/Unit/DeliveryOperations/DTO/DeliveryOperationsAdminPageResultDTOTest.php`
- `tests/Unit/DeliveryOperations/Exception/DeliveryOperationsAdminQueryExecutionExceptionTest.php`
- `tests/Unit/DeliveryOperations/Exception/DeliveryOperationsAdminQueryInvalidArgumentExceptionTest.php`
- `tests/Unit/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsRowMapperTest.php`
- `tests/Unit/DeliveryOperations/Infrastructure/Mysql/Pagination/DeliveryOperationsAdminQueryDescriptorBuilderTest.php`
- `tests/Unit/DeliveryOperations/Infrastructure/Mysql/DeliveryOperationsAdminQueryMysqlRepositoryTest.php`
- `tests/Regression/DeliveryOperations/DeliveryOperationsQueryMysqlRepositoryRegressionTest.php`
- `tests/Integration/DeliveryOperations/DeliveryOperationsAdminQueryMysqlRepositoryTest.php`

### 6.3 Required Later Runtime Sequence
1. public contracts, DTO validation/serialization, and exceptions;
2. policy-free mapper and descriptor builder;
3. Admin MySQL repository and Unit exception/execution gates;
4. **Regression/protected-contract gate:** explicitly cover primitive query constructor/signature/filters/cursor/order/limit/hydration/exceptions, writer/recorder/policy/factory/provider/bindings, schema, and fail-open behavior;
5. **Strict Integration gate:** explicitly cover every equality/range/null/text/metadata filter independently and in combinations, exact microseconds, total/filtered/data alignment, pagination/clamping/overflow/tie-breaks, native prepared statements, transaction preservation. Unindexed-filter scan implications are tested, not skipped.
6. final package/integration/domain documentation (e.g., `README.md`, `ADMIN_READ_USAGE.md`).

### 6.4 Strict Integration Requirements
- Missing/empty `EVENT_LOGGING_TEST_MYSQL_DSN` -> throws `RuntimeException`. No `markTestSkipped()`.
- Must execute against native MySQL using:
  ```php
  PDO::ATTR_EMULATE_PREPARES => false
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ```

### 6.5 Documentation Files Updated in Later Runtime PR
```text
EVENT_LOGGING_PACKAGE_REFERENCE.md
CHANGELOG.md
src/DeliveryOperations/README.md
docs/integration/ADMIN_READ_USAGE.md
docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md
docs/architecture/ADMIN_QUERY_DELIVERY_OPERATIONS_BLUEPRINT.md
docs/roadmap/ADMIN_QUERY_API_ROADMAP.md
docs/audits/DOCUMENTATION_INVENTORY.md
```
