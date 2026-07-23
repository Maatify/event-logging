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

interface DeliveryOperationsAdminQueryInterface
{
    /**
     * @throws DeliveryOperationsStorageException
     * @throws DeliveryOperationsAdminQueryExecutionException
     */
    public function paginate(DeliveryOperationsAdminQueryRequestDTO $request): DeliveryOperationsAdminPageResultDTO;
}
```

### 3.2 Exceptions
- `DeliveryOperationsAdminQueryInvalidArgumentException`: extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException`, implements `EventLoggingExceptionInterface`. Emits domain-specific validation error messages.
- `DeliveryOperationsAdminQueryExecutionException`: extends `Maatify\Exceptions\Exception\System\SystemMaatifyException`, implements `EventLoggingExceptionInterface`. Uses `ErrorCodeEnum::MAATIFY_ERROR`. Emits domain-specific execution messages.

### 3.3 Request DTO
`DeliveryOperationsAdminQueryRequestDTO`
Constructed with all valid filter dimensions. Strings must be trimmed and UTF-8 validated. Short invalid sort values map to `null`.
```php
namespace Maatify\EventLogging\DeliveryOperations\DTO;

use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;

final readonly class DeliveryOperationsAdminQueryRequestDTO
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
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
        public ?int $attemptNo = null,
        public ?string $correlationId = null,
        public ?string $requestId = null,
        public ?string $provider = null,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?array $metadata = null, // key-value equality assertions
        public ?\DateTimeImmutable $scheduledAfter = null,
        public ?\DateTimeImmutable $scheduledBefore = null,
        public ?\DateTimeImmutable $completedAfter = null,
        public ?\DateTimeImmutable $completedBefore = null,
        public ?\DateTimeImmutable $occurredAfter = null,
        public ?\DateTimeImmutable $occurredBefore = null
    ) {
        // Full normalization and strict validation enforced here.
    }
}
```

### 3.4 Request Validation and Semantics
- **Length Limits**: eventId (36), channel (32), operationType (64), actorType (32), targetType (64), status (32), correlationId (36), requestId (64), provider (64), providerMessageId (128), errorCode (64), errorMessage (n/a - bounded by max PHP length or explicit limit up to 16MB but practically smaller).
- **Independence**: `actorType`/`actorId` and `targetType`/`targetId` are independently filterable. Positive integers for IDs.
- **Ranges**: `after` must be `<= before`.
- **Pagination Limits**: `perPage` 1 to 200.
- **Text Search**: `errorMessage` uses safe LIKE search with escaping (`LIKE :error_message_like`).
- **Metadata JSON Search**: Package-owned simple key-value equality (e.g. `JSON_EXTRACT(metadata, :json_path) = :json_value`).

### 3.5 Result DTO
`DeliveryOperationsAdminPageResultDTO`
Must match the exact structure of other domains.
```php
namespace Maatify\EventLogging\DeliveryOperations\DTO;

final readonly class DeliveryOperationsAdminPageResultDTO implements \JsonSerializable
{
    /**
     * @param array<DeliveryOperationsViewDTO> $items
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
        public ?string $sortBy,
        public ?string $sortDirection
    ) {}

    // Strict jsonSerialize() implementation maintaining ordered output.
}
```

## 4. Internal Mechanics and SQL

### 4.1 SQL Columns
Explicit selection without `SELECT *`:
`id, event_id, channel, operation_type, actor_type, actor_id, target_type, target_id, status, attempt_no, scheduled_at, completed_at, correlation_id, request_id, provider, provider_message_id, error_code, error_message, metadata, occurred_at`

### 4.2 SQL Clauses and Placeholders
The Descriptor Builder (`DeliveryOperationsAdminQueryDescriptorBuilder`) generates:
- `whereSql`: Starts with ` WHERE ` and uses ` AND ` for conditions. No trailing characters if empty.
- Parameters must use exact unique named placeholders (e.g., `:event_id`, `:actor_type`, `:completed_before`).
- `metadata` filter uses exact SQL/JSON safe extraction for the specific engine supported (MySQL).
- No `ORDER BY`, `LIMIT`, or `OFFSET` in `dataSql`.
- UTC formatting `Y-m-d H:i:s.u`.
- Date placeholders: e.g., `:occurred_after`, `:occurred_before`.

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
It safely handles JSON metadata string decoding, fallback values, and policy normalization if any policy exists.

## 5. Verification Matrix (Test Design)

### 5.1 Unit Tests
- `DeliveryOperationsAdminQueryRequestDTOTest`: Every normalization and validation rule (UTF-8 length, invalid dates, sorting logic).
- `DeliveryOperationsAdminPageResultDTOTest`: Exact serialization order.
- `DeliveryOperationsAdminQueryDescriptorBuilderTest`: Descriptor generation (explicit columns, exactly correct placeholders, no offset/limit, metadata JSON search).
- `DeliveryOperationsRowMapperTest`: JSON fallback, types, null handling.
- `DeliveryOperationsAdminQueryMysqlRepositoryTest`: Exception translation and delegation assertions.
- `DeliveryOperationsAdminQueryExceptionTest`: Formatting and previous preservation.

### 5.2 Regression Tests
- `DeliveryOperationsQueryMysqlRepositoryRegressionTest`: Prove primitive `find()`, limits, exception boundaries, constructor preserved.

### 5.3 Strict MySQL Integration Tests
- `DeliveryOperationsAdminQueryMysqlRepositoryTest`: Strict database test.
- Must execute against native MySQL (disabling emulated prepares, using `FETCH_ASSOC` and `ERRMODE_EXCEPTION`).
- Every independent filter, range boundaries, complex combinations.
- Metadata JSON exact filtering.
- Pagination navigation, overflow, deterministic tie-breaking (`id DESC`).
- Failure mapping (e.g., forced schema/table missing or invalid SQL context for `PDOException`).
