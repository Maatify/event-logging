# SecuritySignals Admin Query Rebuild Blueprint

**Status:** Owner Approved / Runtime Added / Strict MySQL Verification Pending

This document defines the complete approved architecture for replacing the superseded post-v1 SecuritySignals pagination wrapper with a package-owned Admin Query API. The Runtime implementation is now present in the package branch, pending successful strict real-MySQL verification before final implementation status.

It records the Owner decisions made on `2026-07-14`, the post-v1 retirement rule recorded by `ADMIN_QUERY_SECURITY_SIGNALS_POST_V1_RETIREMENT_DECISION.md`, and final approval of the complete coherent blueprint on `2026-07-15`. It authorizes a separate Runtime implementation task/PR, but it does **not** itself implement Runtime, tagging, or release work.

---

## 1. Audited Baseline

- **Audit date:** `2026-07-14` (UTC)
- **Exact audited `main` SHA:** `3169947e107df66f61884abb5c95f1dfa621a69b`
- **Original PR #102 HEAD:** `d5121cee3a3069aaaaea5dded2521ae1316f5fdb`
- **Pre-Owner-decision corrected HEAD:** `c4f3b234e1d82b0b08b8207c76b64f46474ec058`
- **Regressed Owner-decision commit inspected:** `0da1f2f28c5146c84a6cada1c0534eabb401c84a`
- **Historical post-v1 pagination origin:** PR #74, `Add SecuritySignals paginated query support`

### 1.1 Runtime and schema sources reviewed

- `src/SecuritySignals/Contract/SecuritySignalsQueryInterface.php`
- `src/SecuritySignals/Contract/SecuritySignalsPolicyInterface.php`
- `src/SecuritySignals/DTO/SecuritySignalsQueryDTO.php`
- `src/SecuritySignals/DTO/SecuritySignalsViewDTO.php`
- `src/SecuritySignals/DTO/SecuritySignalRecordDTO.php`
- `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsQueryMysqlRepository.php`
- `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsLoggerMysqlRepository.php`
- `src/SecuritySignals/Recorder/SecuritySignalsRecorder.php`
- `src/SecuritySignals/Recorder/SecuritySignalsDefaultPolicy.php`
- `src/SecuritySignals/Exception/SecuritySignalsStorageException.php`
- `src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql`
- `src/Bootstrap/EventLoggingBindings.php`
- `src/Factory/SecuritySignalsFactory.php`

### 1.2 Tests reviewed

- `tests/Unit/SecuritySignals/Repository/SecuritySignalsQueryMysqlRepositoryTest.php`
- `tests/Integration/SecuritySignals/SecuritySignalsRepositoryTest.php`
- `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php`
- `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php`
- `tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php`

### 1.3 Governing documents reviewed

- `AGENTS.md`
- `EVENT_LOGGING_PACKAGE_REFERENCE.md`
- `docs/standards/PACKAGE_BUILDING_STANDARD.md`
- `docs/standards/COMPOSER_PACKAGE_STANDARD.md`
- `docs/standards/CI_WORKFLOW_STANDARD.md`
- `docs/standards/LIBRARY_PRESENTATION_STANDARD.md`
- `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md`
- `docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md`
- `docs/architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md`
- `docs/architecture/ADMIN_QUERY_SECURITY_SIGNALS_POST_V1_RETIREMENT_DECISION.md`
- `docs/architecture/PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md`
- `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`
- `docs/audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md`
- `docs/audits/DOCUMENTATION_INVENTORY.md`
- `docs/integration/ADMIN_READ_USAGE.md`

### 1.4 Required separation

The audit distinguishes three different contracts:

1. **Protected `v1.0.0` Runtime**
   - primitive public interface;
   - primitive query and view DTOs;
   - primitive repository constructor and observable behavior;
   - schema;
   - row hydration;
   - storage exception boundary;
   - write-side policy behavior;
   - recorder fail-open reliability boundary.
2. **Superseded post-v1 pagination experiment**
   - four Runtime wrapper artifacts;
   - three directly associated unit tests;
   - introduced by PR #74;
   - not part of the protected `v1.0.0` surface;
   - replaced and deleted atomically inside the approved Runtime rebuild.
3. **Approved Admin Query path**
   - SecuritySignals-specific package API;
   - offset/page pagination through `maatify/persistence`;
   - no change to the primitive public contract.

Current code, current schema, active canonical documents, the post-v1 retirement decision, and already implemented package-owned Admin Query patterns take precedence over historical documents.

---

## 2. Protected Primitive Contract

### 2.1 Public interface

The protected method is exactly:

```php
public function find(SecuritySignalsQueryDTO $query): array;
```

The return contract is:

```php
/** @return array<SecuritySignalsViewDTO> */
```

`SecuritySignals` has no primitive `read()` method.

The method may throw `SecuritySignalsStorageException`.

### 2.2 Query DTO

The protected constructor order and defaults are exactly:

```php
public function __construct(
    public ?\DateTimeImmutable $after = null,
    public ?\DateTimeImmutable $before = null,
    public ?string $actorType = null,
    public ?int $actorId = null,
    public ?string $signalType = null,
    public ?string $severity = null,
    public ?string $requestId = null,
    public ?string $correlationId = null,
    public ?\DateTimeImmutable $cursorOccurredAt = null,
    public ?int $cursorId = null,
    public int $limit = 50,
)
```

The protected serialized keys and order are:

```text
after
before
actorType
actorId
signalType
severity
requestId
correlationId
cursorOccurredAt
cursorId
limit
```

Dates serialize using `DATE_ATOM`.

### 2.3 View DTO

The protected constructor and serialized field order is exactly:

```text
id
eventId
actorType
actorId
signalType
severity
correlationId
requestId
routeName
ipAddress
userAgent
metadata
occurredAt
```

`occurredAt` serializes using `DATE_ATOM`.

### 2.4 Repository constructor

The protected constructor is exactly:

```php
public function __construct(private readonly PDO $pdo)
```

The primitive constructor accepts only `PDO`.

It must not gain a policy, mapper, paginator, descriptor builder, logger, or testing seam as a public constructor dependency.

### 2.5 Filters and SQL behavior

The primitive repository supports exactly:

| Query field | SQL condition |
|---|---|
| `actorType` | `actor_type = :actor_type` |
| `actorId` | `actor_id = :actor_id` |
| `signalType` | `signal_type = :signal_type` |
| `severity` | `severity = :severity` |
| `requestId` | `request_id = :request_id` |
| `correlationId` | `correlation_id = :correlation_id` |
| `after` | `occurred_at >= :after` |
| `before` | `occurred_at <= :before` |

Date parameters use:

```text
Y-m-d H:i:s.u
```

The cursor condition is activated only when both `cursorOccurredAt` and `cursorId` are non-null.

The protected ordering is:

```sql
ORDER BY occurred_at DESC, id DESC
```

The protected limit behavior is:

```php
$limit = max(1, $query->limit);
```

The primitive path currently uses `SELECT *`. That existing observable behavior is preserved. The new Admin Query path must use an explicit selected-column list.

The primitive repository does not begin, commit, or roll back transactions.

### 2.6 Row iteration and hydration

The protected behavior is:

- rows returned by `fetchAll(PDO::FETCH_ASSOC)` that are not arrays are skipped;
- numeric `id` maps to `int`; otherwise `0`;
- string `event_id` maps to `eventId`; otherwise `''`;
- string `actor_type` maps to `actorType`; otherwise `null`;
- numeric `actor_id` maps to `int`; otherwise `null`;
- string `signal_type` maps to `signalType`; otherwise `''`;
- string `severity` maps to `severity`; otherwise `''`;
- string `correlation_id` maps to `correlationId`; otherwise `null`;
- string `request_id` maps to `requestId`; otherwise `null`;
- string `route_name` maps to `routeName`; otherwise `null`;
- string `ip_address` maps to `ipAddress`; otherwise `null`;
- string `user_agent` maps to `userAgent`; otherwise `null`;
- missing or non-string `occurred_at` is parsed from `1970-01-01 00:00:00` using UTC;
- invalid persisted date text throws during row mapping;
- missing, non-string, or empty `metadata` maps to `null`;
- malformed JSON maps to `null`;
- scalar JSON maps to `null`;
- a JSON array containing any numeric key maps to `null`;
- an associative JSON object maps to `array<string, mixed>`.

### 2.7 Exception boundary

The protected query failure prefix is exactly:

```text
Failed to query SecuritySignals records: 
```

The protected row-mapping failure prefix is exactly:

```text
Failed to map SecuritySignals row: 
```

The primitive repository:

- catches `PDOException` for query execution;
- catches `Throwable` for row mapping;
- preserves the original throwable as `previous`.

A future mapper extraction must not alter the constructor, public signatures, filters, cursor activation, ordering, limit behavior, hydration fallbacks, catch boundaries, or message prefixes.

---

## 3. SecuritySignals Schema Contract

The exact table is:

```text
maa_event_logging_security_signals
```

### 3.1 Columns

| Column | SQL type | Nullability / constraint |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | primary key |
| `event_id` | `CHAR(36)` | `NOT NULL` |
| `actor_type` | `VARCHAR(32)` | `NOT NULL` |
| `actor_id` | `BIGINT` | `NULL` |
| `signal_type` | `VARCHAR(100)` | `NOT NULL` |
| `severity` | `VARCHAR(16)` | `NOT NULL` |
| `correlation_id` | `CHAR(36)` | `NULL` |
| `request_id` | `VARCHAR(64)` | `NULL` |
| `route_name` | `VARCHAR(255)` | `NULL` |
| `ip_address` | `VARCHAR(45)` | `NULL` |
| `user_agent` | `VARCHAR(512)` | `NULL` |
| `metadata` | `JSON` | `NOT NULL` |
| `occurred_at` | `DATETIME(6)` | `NOT NULL` |

Unique constraint:

```text
uq_el_security_signals_event_id (event_id)
```

### 3.2 Indexes

```text
idx_el_security_signals_time
    (occurred_at, id)

idx_el_security_signals_actor_time
    (actor_type, actor_id, occurred_at)

idx_el_security_signals_type_time
    (signal_type, occurred_at)

idx_el_security_signals_severity_time
    (severity, occurred_at)

idx_el_security_signals_corr_time
    (correlation_id, occurred_at)

idx_el_security_signals_request_time
    (request_id, occurred_at)
```

`actor_type`, `signal_type`, `severity`, `correlation_id`, and `request_id` are leftmost indexed dimensions.

`actor_id` without `actor_type` cannot use the leftmost prefix of `idx_el_security_signals_actor_time` and may require a wider scan. This is an accepted performance implication, not a public API validation restriction.

### 3.3 Data boundary

The table is non-authoritative and best-effort.

SecuritySignals recording failures must not affect application control flow.

`metadata` must not contain passwords, OTP codes, tokens, credentials, or other secrets.

---

## 4. Write Policy and Reliability Boundary

`SecuritySignalsPolicyInterface` belongs to the write/recording path.

It:

- normalizes actor type;
- normalizes severity;
- validates metadata JSON size.

`SecuritySignalsDefaultPolicy` falls back to:

- `ANONYMOUS` for invalid actor type;
- `INFO` for invalid severity;
- maximum metadata JSON size of `65535` bytes.

`SecuritySignalsRecorder` catches `Throwable` across command construction, policy normalization, metadata processing, record DTO construction, writer execution, and fallback logging.

Recording remains fail-open.

The primitive query repository does not use `SecuritySignalsPolicyInterface`.

Therefore, the proposed shared read mapper is policy-free:

- no policy constructor;
- no actor-type normalization during reads;
- no severity normalization during reads;
- no metadata-size validation during reads;
- exact preservation of current persisted-row fallbacks.

Admin Query calls are direct read operations. Their validation, execution, and storage exceptions are exposed to their caller; they are not converted into recorder-style fail-open behavior.

---

## 5. Superseded Post-v1 Pagination Experiment

PR #74 introduced exactly these Runtime artifacts:

```text
src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php
src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php
src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php
src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php
```

It introduced exactly these tests:

```text
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php
tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php
```

Classification:

```text
Superseded Post-v1 Experiment
```

Known package references are limited to the wrapper family itself, its tests, and architecture/history documentation:

- the service implements `SecuritySignalsPaginatedQueryInterface`;
- the service depends on the protected primitive query interface;
- the interface references the primitive query DTO and wrapper page DTO;
- the page DTO references the wrapper cursor DTO and primitive view DTO;
- the three unit tests directly exercise the wrapper family;
- package reference, architecture, roadmap, and compatibility documents describe the family or its remediation state.

No current default factory, provider, bootstrap binding, or package example makes this wrapper the approved Admin Query target.

The wrapper interface, service shape, constructors, page DTO, cursor DTO, serialized keys, cursor-generation approach, wrapper pagination semantics, and coupling to the primitive query path are **not** protected compatibility targets.

### 5.1 Atomic retirement and host repository handling

This is a public library. Maintained host repositories must be searched for imports, construction, interface implementation, service calls, DTO usage, factories, bindings, tests, and documentation references.

The known current state is that the superseded wrapper has not been fully adopted by any host project.

Any discovered host use must be migrated to the approved Admin Query API and verified in the relevant host repository.

However:

- host search and migration do not convert the superseded package artifacts into protected contracts;
- host search and migration do not postpone package-level deletion;
- the seven superseded Runtime/test artifacts are deleted in the same SecuritySignals Runtime rebuild change set that adds and verifies the replacement;
- host migration may be delivered through coordinated host-repository PRs against the package version containing the rebuilt API;
- retaining the obsolete wrapper as an active or deprecated compatibility layer requires a new explicit Owner decision.

The package-level Runtime rebuild is atomic:

1. add the approved SecuritySignals Admin Query implementation;
2. add its Unit, Regression, and strict real-MySQL Integration coverage;
3. preserve the protected `v1.0.0` primitive behavior;
4. delete the exact seven superseded post-v1 artifacts;
5. update package and integration documentation.

PR #102 and PR #103 were documentation-only and implemented no deletion themselves.

---

## 6. Approved Public Admin Query Interface

The public interface is:

```php
namespace Maatify\EventLogging\SecuritySignals\Contract;

use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminPageResultDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryExecutionException;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsAdminQueryInvalidArgumentException;
use Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException;

interface SecuritySignalsAdminQueryInterface
{
    /**
     * @throws SecuritySignalsAdminQueryInvalidArgumentException
     * @throws SecuritySignalsAdminQueryExecutionException
     * @throws SecuritySignalsStorageException
     */
    public function paginate(
        SecuritySignalsAdminQueryRequestDTO $request,
    ): SecuritySignalsAdminPageResultDTO;
}
```

The method name is `paginate()`.

No `maatify/persistence` class appears in the public interface.

---

## 7. Approved Request DTO Contract

The request DTO is:

```php
namespace Maatify\EventLogging\SecuritySignals\DTO;

use DateTimeImmutable;
use JsonSerializable;

final readonly class SecuritySignalsAdminQueryRequestDTO implements JsonSerializable
{
    public ?string $actorType;
    public ?int $actorId;
    public ?string $signalType;
    public ?string $severity;
    public ?string $requestId;
    public ?string $correlationId;
    public ?DateTimeImmutable $after;
    public ?DateTimeImmutable $before;
    public int|string|null $page;
    public int|string|null $perPage;
    public ?string $sortBy;
    public ?string $sortDirection;

    public function __construct(
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $signalType = null,
        ?string $severity = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?DateTimeImmutable $after = null,
        ?DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
    ) {
        // Exact rules are defined below.
    }
}
```

The exact serialized keys and order are:

```text
actorType
actorId
signalType
severity
requestId
correlationId
after
before
page
perPage
sortBy
sortDirection
```

`after` and `before` serialize using `DATE_ATOM`.

### 7.1 String normalization

For `actorType`, `signalType`, `severity`, `requestId`, `correlationId`, `sortBy`, and `sortDirection`:

1. `null` remains `null`;
2. trim the string;
3. an empty trimmed string becomes `null`;
4. validate UTF-8 without requiring `ext-mbstring` using the established `/./us` `preg_match_all` pattern;
5. reject values longer than the approved maximum.

Maximum lengths:

| Field | Maximum |
|---|---:|
| `actorType` | 32 |
| `signalType` | 100 |
| `severity` | 16 |
| `requestId` | 64 |
| `correlationId` | 36 |
| `sortBy` | 64 |
| `sortDirection` | 4 |

### 7.2 ID and actor-filter rules

- `actorId`, when non-null, must be greater than zero;
- a positive `actorId` is valid when `actorType` is `null`;
- `actorType` is valid when `actorId` is `null`;
- both may be supplied together;
- neither is required.

This independent-filter contract is intentional because the package is a general-purpose library and host systems may identify actors by type and ID, type only, ID only, or neither filter.

The wider-scan risk for `actorId` without `actorType` is documented but is not converted into a validation error.

### 7.3 Date and page rules

- if both dates are supplied, `after` must be less than or equal to `before`;
- equal boundaries are valid;
- `page` remains `int|string|null`;
- `perPage` remains `int|string|null`;
- page normalization, per-page clamping, and offset calculation are delegated to `maatify/persistence`.

### 7.4 Sort normalization

Public caller-selectable sorting is limited to:

```text
occurred_at
```

Rules:

```text
sortBy = occurred_at -> occurred_at
sortBy = id          -> null
other short value    -> null
overlong value       -> invalidLength(sortBy)
invalid UTF-8        -> invalidEncoding(sortBy)

sortDirection = asc  -> ASC
sortDirection = desc -> DESC
other short value    -> null
overlong value       -> invalidLength(sortDirection)
invalid UTF-8        -> invalidEncoding(sortDirection)
```

`id` is an internal deterministic tie-breaker only.

---

## 8. Approved Page Result DTO Contract

The result DTO is:

```php
namespace Maatify\EventLogging\SecuritySignals\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * @implements IteratorAggregate<int, SecuritySignalsViewDTO>
 */
final readonly class SecuritySignalsAdminPageResultDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<SecuritySignalsViewDTO> $items
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
     * @return ArrayIterator<int, SecuritySignalsViewDTO>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
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

There is no root-level `id` field.

---

## 9. Approved Runtime Components and Visibility

### 9.1 Public infrastructure adapter

```text
src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsAdminQueryMysqlRepository.php
```

It is a public package infrastructure adapter implementing `SecuritySignalsAdminQueryInterface`.

Approved constructor:

```php
public function __construct(private PDO $pdo)
```

The constructor accepts only `PDO`.

The mapper, descriptor builder, paginator, sort whitelist, and pagination configuration are created internally.

No policy, injectable paginator, or production testing seam is authorized as a public constructor parameter.

### 9.2 Internal implementation details

```text
src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsRowMapper.php

src/SecuritySignals/Infrastructure/Mysql/Pagination/
SecuritySignalsAdminQueryDescriptorBuilder.php
```

The row mapper and descriptor builder are marked `@internal`.

The Admin repository is not marked `@internal`.

### 9.3 Repository responsibilities

The Admin repository:

- creates `PageRequest` from request pagination fields;
- builds the SecuritySignals descriptor;
- creates canonical pagination configuration;
- invokes `PdoPaginator`;
- maps each data row through `SecuritySignalsRowMapper`;
- returns `SecuritySignalsAdminPageResultDTO`;
- owns no transaction.

---

## 10. Policy-Free Shared Row Mapper

Approved mapper contract:

```php
/** @internal */
final class SecuritySignalsRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): SecuritySignalsViewDTO
    {
        // Must reproduce the protected primitive mapping exactly.
    }
}
```

The mapper:

- has no policy constructor;
- applies no read-time actor-type normalization;
- applies no read-time severity normalization;
- applies no read-time metadata-size validation;
- preserves every primitive fallback listed in Section 2.6;
- throws on invalid persisted date text exactly as the current primitive mapping does.

A primitive refactor may delegate row construction to this mapper only after regression tests prove identical behavior.

The repository translates mapper failures using:

```text
Failed to map SecuritySignals row: {original message}
```

A `SecuritySignalsStorageException` already produced inside the mapping callback must propagate unchanged and must not be rewrapped as a pagination execution failure.

---

## 11. Exact SQL and Descriptor Contract

### 11.1 Shared filter source

The descriptor builder generates one shared structure:

```php
array{
    whereSql: string,
    params: array<string, string|int|bool|null>
}
```

The same `whereSql` and parameters are used by filtered-count and data SQL.

Parameter-array keys do not contain leading colons.

Accepted filters map exactly as follows:

| Request field | SQL |
|---|---|
| `actorType` | `actor_type = :actor_type` |
| `actorId` | `actor_id = :actor_id` |
| `signalType` | `signal_type = :signal_type` |
| `severity` | `severity = :severity` |
| `requestId` | `request_id = :request_id` |
| `correlationId` | `correlation_id = :correlation_id` |
| `after` | `occurred_at >= :after` |
| `before` | `occurred_at <= :before` |

Dates are converted to UTC and formatted using:

```text
Y-m-d H:i:s.u
```

`DATE_ATOM` is used only by DTO JSON serialization.

### 11.2 Total count SQL

```sql
SELECT COUNT(*)
FROM maa_event_logging_security_signals
```

There are no package-owned mandatory row-visibility constraints for SecuritySignals. Therefore, `total` counts all rows.

### 11.3 Filtered count SQL

```sql
SELECT COUNT(*)
FROM maa_event_logging_security_signals
{whereSql}
```

### 11.4 Data SQL

```sql
SELECT
    id,
    event_id,
    actor_type,
    actor_id,
    signal_type,
    severity,
    correlation_id,
    request_id,
    route_name,
    ip_address,
    user_agent,
    metadata,
    occurred_at
FROM maa_event_logging_security_signals
{whereSql}
```

The Admin Query descriptor uses an explicit 13-column selection.

It must not use `SELECT *`.

The descriptor data SQL must not contain:

```text
ORDER BY
LIMIT
OFFSET
```

Those mechanics belong to `maatify/persistence`.

### 11.5 Unsupported package filters

The Admin request does not support:

```text
eventId
routeName
ipAddress
userAgent
metadata
free-text search
arbitrary column names
arbitrary SQL expressions
```

These values remain present in returned `SecuritySignalsViewDTO` items where applicable.

---

## 12. Pagination Configuration

Generic page mechanics are delegated to `maatify/persistence ^1.1.0`.

Canonical configuration:

```text
public sort key:
    occurred_at

internal sort mapping:
    occurred_at -> occurred_at
    id          -> id

default sort:
    occurred_at DESC

tie-breaker:
    id DESC

default per page:
    20

minimum per page:
    1

maximum per page:
    200
```

The public EventLogging interface and DTOs expose no persistence package classes.

`total`, `filtered`, data ordering, page normalization, per-page clamping, offset calculation, `LIMIT`, `OFFSET`, and canonical pagination metadata are produced through the persistence package.

---

## 13. Repository Exception Boundary

Storage execution failures:

```text
PaginationExecutionException
PDOException
```

translate to `SecuritySignalsStorageException` using:

```text
Failed to query SecuritySignals records: {original message}
```

Pagination configuration and query/descriptor validation failures:

```text
InvalidPaginationConfigurationException
InvalidPaginationQueryException
```

translate to `SecuritySignalsAdminQueryExecutionException`.

Mapper failures translate to `SecuritySignalsStorageException` using:

```text
Failed to map SecuritySignals row: {original message}
```

The original throwable is preserved as `previous`.

Storage failures must not be translated into `SecuritySignalsAdminQueryExecutionException`.

---

## 14. Approved Exception Classes and Exact Messages

### 14.1 Invalid argument exception

Approved class:

```text
SecuritySignalsAdminQueryInvalidArgumentException
```

It:

- extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException`;
- implements `Maatify\EventLogging\Exception\EventLoggingExceptionInterface`.

Exact factories and messages:

```text
invalidId(field)
    Invalid SecuritySignals Admin Query ID: {field}

invalidLength(field)
    Invalid SecuritySignals Admin Query length: {field}

invalidEncoding(field)
    Invalid SecuritySignals Admin Query UTF-8 encoding: {field}

invalidDateRange()
    Invalid SecuritySignals Admin Query date range: after must be before or equal to before
```

`actorId` without `actorType` is not an invalid-ID condition.

### 14.2 Execution exception

Approved class:

```text
SecuritySignalsAdminQueryExecutionException
```

It:

- extends `SystemMaatifyException`;
- implements `EventLoggingExceptionInterface`;
- preserves the previous throwable;
- uses `ErrorCodeEnum::MAATIFY_ERROR`.

Exact approved message:

```text
SecuritySignals Admin Query execution failed: {original message}
```

### 14.3 Storage exception

The existing `SecuritySignalsStorageException` remains the storage boundary.

Its class, error code, and primitive behavior are not redesigned.

---

## 15. Required Native-PDO Primitive Cursor Correction

The current primitive SQL reuses the named placeholder `:cursor_at` twice.

Repeated named placeholders are prohibited by the project standard and are not an Owner choice.

The Runtime implementation must use:

```sql
(
    occurred_at < :cursor_at_before
    OR (
        occurred_at = :cursor_at_equal
        AND id < :cursor_id
    )
)
```

Both timestamp parameters receive the same UTC-formatted value.

This required correction:

- does not change the primitive public interface;
- does not change cursor activation;
- does not change ordering;
- does not change limit behavior;
- does not change page semantics;
- does not change returned records;
- must be delivered with focused unit coverage;
- must be delivered with primitive regression coverage;
- must be proven using real MySQL with native prepared statements.

PR #102 documented the requirement but did not implement it.

---

## 16. Required Test and Compatibility Matrix

### 16.1 Request DTO unit tests

- exact constructor field order and defaults;
- exact serialized key order;
- `DATE_ATOM` serialization;
- nullable values;
- trimming;
- empty-string normalization;
- UTF-8 validation without `ext-mbstring`;
- every maximum-length boundary;
- every over-limit case;
- positive `actorId`;
- zero `actorId` rejection;
- negative `actorId` rejection;
- `actorId` without `actorType` accepted;
- `actorType` without `actorId` accepted;
- both actor filters accepted together;
- neither actor filter accepted;
- equal date boundaries;
- invalid date order;
- page/per-page passthrough;
- valid public sort;
- invalid short sort normalization;
- overlong sort rejection;
- invalid UTF-8 sort rejection;
- case-insensitive direction normalization.

### 16.2 Result DTO unit tests

- constructor values;
- exact serialized root keys;
- exact serialized key order;
- iterator behavior;
- empty items;
- canonical pagination metadata;
- no root-level `id`.

### 16.3 Row mapper unit tests

- every field mapped normally;
- numeric `id` fallback;
- `event_id` fallback;
- nullable `actor_type` fallback;
- numeric `actor_id` fallback;
- `signal_type` fallback;
- `severity` fallback;
- nullable context fields;
- missing/non-string date fallback;
- invalid date failure;
- associative metadata;
- missing metadata;
- non-string metadata;
- empty metadata;
- malformed JSON;
- scalar JSON;
- numeric-key JSON array;
- mapper has no policy dependency.

### 16.4 Descriptor and pagination unit tests

- total SQL exactly matches the table-only count;
- filtered SQL uses the generated `WHERE`;
- data SQL uses the explicit 13-column list;
- no `SELECT *`;
- no `ORDER BY`, `LIMIT`, or `OFFSET` in descriptor SQL;
- every filter individually;
- `actorId` filter without `actorType`;
- `actorType` filter without `actorId`;
- actor pair together;
- combined non-actor filters;
- all filters together;
- exact parameter names;
- parameter keys without leading colons;
- exact parameter values;
- UTC date conversion;
- `Y-m-d H:i:s.u` SQL formatting;
- inclusive date boundaries;
- equal date boundaries;
- filtered-count/data `WHERE` identity;
- filtered-count/data parameter identity;
- public sort whitelist behavior;
- internal tie-breaker mapping;
- default and tie-breaker directions;
- default/minimum/maximum per-page configuration.

### 16.5 Repository unit tests

- `PDO`-only constructor;
- page request creation;
- paginator result mapping;
- result DTO mapping;
- no transaction ownership;
- PDO failure storage translation;
- pagination execution storage translation;
- invalid pagination configuration execution translation;
- invalid pagination query execution translation;
- row-mapping prefix;
- query prefix;
- previous throwable preservation;
- mapper storage exception propagation without rewrapping.

### 16.6 Primitive regression and retirement tests

- `SecuritySignalsQueryInterface::find()` signature;
- primitive DTO constructor order;
- primitive DTO defaults;
- primitive DTO serialization;
- primitive view DTO constructor;
- primitive view DTO serialization;
- primitive repository `PDO`-only constructor;
- every primitive filter;
- independent primitive actor filters;
- cursor condition only when both cursor values exist;
- distinct cursor timestamp placeholders;
- both timestamp parameters receive the same value;
- `occurred_at DESC, id DESC`;
- `max(1, limit)`;
- non-array row skipping;
- all hydration fallbacks;
- all metadata JSON behaviors;
- exact query prefix;
- exact mapping prefix;
- previous throwable preservation;
- no transaction ownership;
- write-side policy behavior unchanged;
- recorder fail-open behavior unchanged;
- exact seven superseded wrapper/test artifacts are absent from the completed Runtime rebuild;
- no package-owned Runtime reference to a deleted wrapper artifact remains;
- no regression test treats wrapper page/cursor contracts or cursor-wrapper mechanics as protected behavior.

### 16.7 Real MySQL integration tests

- live MySQL only;
- no SQLite fallback;
- native prepared statements;
- missing DSN fails the strict gate;
- connection failure fails the strict gate;
- no silent integration skip reported as success;
- every Admin filter separately;
- `actorId` without `actorType`;
- `actorType` without `actorId`;
- actor pair together;
- combined filters;
- inclusive `after` and `before`;
- equal date boundaries;
- zero rows;
- `total` versus `filtered`;
- multiple pages;
- page normalization;
- per-page clamping;
- same-timestamp deterministic ordering by `id DESC`;
- nullable fields;
- metadata hydration;
- primitive distinct-placeholder correction;
- primitive/Admin semantic alignment;
- repository does not own the caller transaction.

### 16.8 Package gates

```text
composer validate --strict
composer analyse
composer test:unit
composer test:regression
real MySQL integration suite
git diff --check
```

A skipped Integration job is not a passing Integration result.

---

## 17. Exact Runtime File Inventory

### 17.1 Required additions

Runtime:

```text
src/SecuritySignals/Contract/SecuritySignalsAdminQueryInterface.php
src/SecuritySignals/DTO/SecuritySignalsAdminQueryRequestDTO.php
src/SecuritySignals/DTO/SecuritySignalsAdminPageResultDTO.php
src/SecuritySignals/Exception/SecuritySignalsAdminQueryInvalidArgumentException.php
src/SecuritySignals/Exception/SecuritySignalsAdminQueryExecutionException.php
src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsAdminQueryMysqlRepository.php
src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsRowMapper.php
src/SecuritySignals/Infrastructure/Mysql/Pagination/SecuritySignalsAdminQueryDescriptorBuilder.php
```

Tests:

```text
tests/Unit/SecuritySignals/DTO/SecuritySignalsAdminQueryRequestDTOTest.php
tests/Unit/SecuritySignals/DTO/SecuritySignalsAdminPageResultDTOTest.php
tests/Unit/SecuritySignals/Exception/SecuritySignalsAdminQueryInvalidArgumentExceptionTest.php
tests/Unit/SecuritySignals/Exception/SecuritySignalsAdminQueryExecutionExceptionTest.php
tests/Unit/SecuritySignals/Infrastructure/Mysql/SecuritySignalsAdminQueryMysqlRepositoryTest.php
tests/Unit/SecuritySignals/Infrastructure/Mysql/SecuritySignalsRowMapperTest.php
tests/Unit/SecuritySignals/Infrastructure/Mysql/Pagination/SecuritySignalsAdminQueryDescriptorBuilderTest.php
tests/Regression/SecuritySignals/SecuritySignalsQueryMysqlRepositoryRegressionTest.php
tests/Integration/SecuritySignals/SecuritySignalsAdminQueryMysqlRepositoryTest.php
tests/Integration/SecuritySignals/SecuritySignalsQueryMysqlRepositoryTest.php
```

### 17.2 Required modifications

```text
src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsQueryMysqlRepository.php
src/SecuritySignals/README.md
EVENT_LOGGING_PACKAGE_REFERENCE.md
docs/integration/ADMIN_READ_USAGE.md
docs/architecture/ADMIN_QUERY_SECURITY_SIGNALS_REBUILD_BLUEPRINT.md
docs/roadmap/ADMIN_QUERY_API_ROADMAP.md
docs/audits/DOCUMENTATION_INVENTORY.md
CHANGELOG.md
```

The primitive repository modification is restricted to:

- shared mapper extraction with exact behavior preservation;
- mandatory distinct cursor timestamp placeholders;
- complete regression and strict real-MySQL coverage.

### 17.3 Required deletions inside the same Runtime rebuild

```text
src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php
src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php
src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php
src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php
tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php
```

The Runtime rebuild must contain both the approved replacement and these exact deletions.

Package-level completion requires:

- replacement Runtime is complete;
- package unit tests pass;
- package regression tests pass;
- strict real MySQL integration tests pass;
- static analysis passes;
- Composer validation passes;
- no package Runtime or active documentation reference presents a deleted artifact as usable API.

Maintained host repositories are searched and any discovered usage is migrated as coordinated integration work. That host work does not preserve the obsolete package API and does not defer the package-level deletion to a later cleanup phase.

### 17.4 Protected unchanged behavior

- primitive public interface and method signature;
- primitive DTO public constructors and serialization;
- primitive repository `PDO`-only constructor;
- primitive filters;
- cursor activation rule;
- ordering;
- limit behavior;
- row hydration;
- storage exception class and prefixes;
- schema;
- writer behavior;
- policy behavior;
- recorder fail-open boundary;
- existing factory/provider/bootstrap behavior unless separately approved.

### 17.5 Explicitly out of scope

```text
schema changes
Composer dependency changes
CI workflow changes
HTTP controllers
routes
middleware
permissions
authentication
UI
localization
exports
dashboards
reporting
free-text search
cross-domain generic repositories
host application wiring inside this package PR
tagging
release publication
```

---

## 18. Owner Decisions and Approval

The following Owner decisions are final and the complete blueprint is approved as one coherent Runtime contract.

### 18.1 Independent actor filters

```text
actorType + actorId: valid
actorType only:      valid
actorId only:        valid
neither:             valid
```

A positive `actorId` must not be rejected because `actorType` is null.

The index implication is documented as a performance consideration only.

### 18.2 Distinct placeholders are mandatory

The primitive repeated `:cursor_at` placeholder must be replaced by:

```text
:cursor_at_before
:cursor_at_equal
```

This is required by the project standard and is not an optional Owner choice.

### 18.3 Atomic post-v1 retirement and host migration

The exact seven superseded artifacts are outside the protected `v1.0.0` contract.

They must be deleted inside the same Runtime rebuild change set that adds and verifies the replacement Admin Query API.

Maintained host repositories must be searched and every discovered use migrated, but:

- host search does not protect the superseded wrapper;
- host migration does not postpone package-level deletion;
- wrapper page/cursor contracts, serialization, and cursor-wrapper mechanics are not compatibility targets;
- retaining the obsolete package API requires a new explicit Owner decision.

### 18.4 Complete blueprint approval

The complete package contract has been reviewed and approved as one coherent blueprint, including:

- public `paginate()` interface;
- request DTO and normalization contract;
- result DTO contract;
- policy-free mapper;
- public repository path and `PDO`-only constructor;
- descriptor and SQL contract;
- pagination configuration;
- exception names and messages;
- exact Runtime file inventory;
- test, atomic-retirement, and host-integration gates.

This approval authorizes a separate Runtime implementation task/PR. It does not authorize deviation from this contract, a tag, or a release.

---

## 19. Runtime Authorization

PR #102 and PR #103 are merged documentation history. They implemented no SecuritySignals Runtime code or artifact deletion.

SecuritySignals Runtime execution is now authorized through a separate task and PR from the latest remote `main`.

The Runtime change must:

1. implement the exact approved Admin Query public and internal contracts in this blueprint;
2. preserve every protected `v1.0.0` primitive, schema, write-policy, and fail-open behavior;
3. apply the behavior-preserving native-PDO distinct-placeholder correction;
4. add the complete Unit, Regression, and strict real-MySQL Integration coverage defined here;
5. add the approved replacement and delete the exact seven superseded Runtime/test artifacts inside the same Runtime rebuild change set;
6. search maintained host repositories and migrate any discovered wrapper usage through coordinated host-repository work without preserving or postponing deletion of the obsolete package API;
7. update the Package Reference, SecuritySignals README, Admin integration documentation, roadmap, documentation inventory, and changelog as applicable;
8. pass Composer validation, PHPStan, Unit, Regression, strict real-MySQL Integration, documentation, architecture-boundary, and `git diff --check` gates.

No tag, release publication, reporting work, dashboard work, schema change, Composer dependency change, CI workflow change, or host framework wiring is authorized by this approval.

Current status:

```text
Owner Approved / Runtime Added / Strict MySQL Verification Pending
```
