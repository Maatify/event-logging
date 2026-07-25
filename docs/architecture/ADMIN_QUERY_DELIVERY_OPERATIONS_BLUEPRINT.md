# DeliveryOperations Admin Query Implementation Blueprint

**Status:** Owner Approved / Runtime Authorized / Implementation Pending

This blueprint defines the precise contracts and boundaries for adding Admin Query API pagination to the `DeliveryOperations` domain.

This is a **New** implementation phase. The `DeliveryOperations` domain never received the incorrect post-v1.0 pagination experiments. The existing primitive Runtime paths (`find()` and `read()`) strictly remain completely untouched.

## 1. Protected Primitive Contracts

The existing primitive Runtime remains protected under `v1.0.0` guarantees.

### Protected Assets
- `DeliveryOperationsQueryInterface`
- `DeliveryOperationsQueryDTO`
- `DeliveryOperationsViewDTO`
- `DeliveryOperationsQueryMysqlRepository` (constructor, public API, and SQL behavior)
- Existing SQL schema, composite indexes, and fail-open constraints
- Write/Recorder contracts and factory bindings

None of these assets may be modified, deprecated, or removed during this task.

## 2. Shared `maatify/persistence` Usage

The new Admin Query API must rely exclusively on `maatify/persistence ^1.1.0`.

The repository must implement offset pagination by instantiating `PdoPaginator` with the database connection and an internally managed `PdoPaginationQueryDescriptor`. The package does not build its own execution loops or calculation logic for pagination bounds.

## 3. Required Interfaces and Exceptions

### Interfaces
```php
namespace Maatify\EventLogging\DeliveryOperations\Contract;

use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminPageResultDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryExecutionException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;

interface DeliveryOperationsAdminQueryInterface
{
    /**
     * @throws DeliveryOperationsAdminQueryInvalidArgumentException
     * @throws DeliveryOperationsAdminQueryExecutionException
     * @throws DeliveryOperationsStorageException
     */
    public function paginate(DeliveryOperationsAdminQueryRequestDTO $request): DeliveryOperationsAdminPageResultDTO;
}
```

### Exceptions
- `DeliveryOperationsAdminQueryInvalidArgumentException`: Thrown during DTO normalization for invalid ranges, unsupported sorts, string length limits, or metadata validation failures. Implements the domain's Validation Exception hierarchy.
- `DeliveryOperationsAdminQueryExecutionException`: Translates `InvalidPaginationConfigurationException` and `InvalidPaginationQueryException`. Implements the domain's Execution Exception hierarchy.
- `DeliveryOperationsStorageException`: Existing domain storage exception. Used to translate `PDOException`, `PaginationExecutionException`, or row mapping failures.

## 4. Query Contracts

### Request DTO (`DeliveryOperationsAdminQueryRequestDTO`)

The DTO must natively handle all supported admin filters for the domain.

```php
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly ?string $eventId = null,
        public readonly ?string $channel = null,
        public readonly ?string $operationType = null,
        public readonly ?string $actorType = null,
        public readonly ?int $actorId = null,
        public readonly ?string $targetType = null,
        public readonly ?int $targetId = null,
        public readonly ?string $status = null,
        public readonly ?int $attemptNoMinimum = null,
        public readonly ?int $attemptNoMaximum = null,
        public readonly ?string $scheduledAtAfter = null,
        public readonly ?string $scheduledAtBefore = null,
        public readonly ?string $completedAtAfter = null,
        public readonly ?string $completedAtBefore = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $requestId = null,
        public readonly ?string $provider = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessageContains = null,
        /** @var array<string, scalar|null>|null */
        public readonly ?array $metadata = null,
        /** @var array<string, bool>|null */
        public readonly ?array $nullStates = null,
        public readonly ?string $occurredAtAfter = null,
        public readonly ?string $occurredAtBefore = null,
        public readonly ?string $sortBy = 'occurred_at',
        public readonly ?string $sortDirection = 'DESC',
    )
```

**Filter Rules:**
- Actor (`actorType`, `actorId`) and Target (`targetType`, `targetId`) can be queried completely independently or combined.
- Dates remain raw strings in the DTO. UTC conversion and validation occur strictly in the descriptor builder.
- `$attemptNoMinimum` and `$attemptNoMaximum` are inclusive. `0` is a valid attempt number.
- `errorMessageContains` performs a `LIKE %...% ESCAPE '\'` search on the full string.
- Unknown sorts silently fallback to `null` to trigger defaults, avoiding validation exceptions on common frontend sort-key mismatches.
- `metadata` strictly enforces `$metadata` keys to match paths like `$.example_key`. Max length 64. Value must be scalar or null. Maximum 5 constraints.
- `nullStates` strictly enforces keys to match camelCase properties. True implies `IS NULL`, False implies `IS NOT NULL`. Maximum 12 whitelisted properties allowed.

### Result DTO (`DeliveryOperationsAdminPageResultDTO`)

Provides canonical page data.

```php
    public function __construct(
        /** @var \Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsViewDTO[] */
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $filtered,
        public readonly int $totalPages,
        public readonly bool $hasNext,
        public readonly bool $hasPrevious,
        public readonly string $sortBy,
        public readonly string $sortDirection,
    )
```

## 5. SQL Implementation details

### Repository (`DeliveryOperationsAdminQueryMysqlRepository`)

Constructs `DeliveryOperationsAdminQueryDescriptorBuilder` and `DeliveryOperationsRowMapper` internally. Injects `PDO` to a parameter-less `PdoPaginator`. Translates mapping and execution exceptions. Never manages its own transaction bounds (`beginTransaction()`, `commit()`, `rollBack()` are strictly forbidden).

### Descriptor Builder (`DeliveryOperationsAdminQueryDescriptorBuilder`)

Maintains exact total/data parity without duplicating conditionals. Uses distinct named placeholders for multiple usage of the same metadata property.

`totalSql`:
```sql
SELECT COUNT(*) FROM maa_event_logging_delivery_operations
```

`filteredCountSql`:
```sql
SELECT COUNT(*) FROM maa_event_logging_delivery_operations {whereSql}
```

`dataSql`:
```sql
SELECT
    id, event_id, channel, operation_type, actor_type, actor_id,
    target_type, target_id, status, attempt_no, scheduled_at,
    completed_at, correlation_id, request_id, provider, provider_message_id,
    error_code, error_message, metadata, occurred_at
FROM maa_event_logging_delivery_operations {whereSql}
```

Never includes `ORDER BY`, `LIMIT`, or `OFFSET`.

**Metadata filtering syntax (strict requirement for MySQL portability without `CAST`):**
```sql
JSON_CONTAINS_PATH(metadata, 'one', :meta_path_exists_X) = 1
AND JSON_CONTAINS(metadata, :meta_value_X, :meta_path_extract_X) = 1
```
Placeholders must be distinct for each path component per `metadata` key iteration.

### Row Mapper (`DeliveryOperationsRowMapper`)

Strictly `final` and `@internal`. Receives raw associative arrays from the paginator. Does not depend on the legacy protected mapper. Fallbacks:
- `missing/non-string/empty/corrupt` arrays map to `null`.
- empty associative structures mapping map to empty `array`.
- invalid dates throw a mapping failure (translated to StorageException).
