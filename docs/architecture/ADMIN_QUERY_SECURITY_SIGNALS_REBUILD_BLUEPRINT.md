# SecuritySignals Admin Query Rebuild Blueprint

**Status:** Proposed / Runtime Implementation Blocked

## 1. Audited Baseline

- **Audit date:** `2026-07-14` (UTC)
- **Exact audited `main` SHA:** `3169947e107df66f61884abb5c95f1dfa621a69b`
- **Original PR #102 HEAD before the first correction:** `d5121cee3a3069aaaaea5dded2521ae1316f5fdb`
- **Corrected PR #102 HEAD audited before this follow-up:** `87e18a22fcd592e88c291dd87826f98ec0972196`
- **Historical post-v1 pagination origin:** PR #74, `Add SecuritySignals paginated query support`

### Runtime and schema sources reviewed

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

### Tests reviewed

- `tests/Unit/SecuritySignals/Repository/SecuritySignalsQueryMysqlRepositoryTest.php`
- `tests/Integration/SecuritySignals/SecuritySignalsRepositoryTest.php`
- `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php`
- `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php`
- `tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php`

### Governing documents reviewed

- `EVENT_LOGGING_PACKAGE_REFERENCE.md`
- `docs/standards/PACKAGE_BUILDING_STANDARD.md`
- `docs/standards/COMPOSER_PACKAGE_STANDARD.md`
- `docs/standards/CI_WORKFLOW_STANDARD.md`
- `docs/standards/LIBRARY_PRESENTATION_STANDARD.md`
- `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md`
- `docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md`
- `docs/architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md`
- `docs/architecture/PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md`
- `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`
- `docs/audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md`
- `docs/audits/DOCUMENTATION_INVENTORY.md`
- `docs/integration/ADMIN_READ_USAGE.md`

### Required separation

1. **Protected `v1.0.0` Runtime:** the primitive public interface, query and view DTOs, repository constructor and behavior, schema, row hydration, storage exception boundary, write-side policy behavior, and recorder reliability boundary.
2. **Superseded post-v1 pagination experiment:** the four wrapper Runtime artifacts and their three tests introduced by PR #74.
3. **Proposed Admin Query path:** a new SecuritySignals-specific offset/page API using `maatify/persistence`, without changing the primitive public contract.

Historical audits are evidence of prior repository states only. Current code, schema, active canonical documents, and the already implemented AuditTrail and BehaviorTrace Admin Query package patterns take precedence.

---

## 2. Protected Primitive Contract

### 2.1 Query interface

The protected public method is exactly:

```php
public function find(SecuritySignalsQueryDTO $query): array;
```

`SecuritySignals` has no primitive `read()` method.

The return contract is:

```php
/** @return array<SecuritySignalsViewDTO> */
```

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

The protected `jsonSerialize()` keys and order are:

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

Dates serialize with `DATE_ATOM`.

### 2.3 View DTO

The protected constructor fields and order are exactly:

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

The protected serialized keys use the same order. `occurredAt` serializes with `DATE_ATOM`.

### 2.4 Primitive repository constructor

The protected constructor is exactly:

```php
public function __construct(private readonly PDO $pdo)
```

No policy, mapper, paginator, descriptor builder, logger, or test seam is accepted.

### 2.5 Primitive filters and SQL behavior

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

The primitive cursor condition is added only when both `cursorOccurredAt` and `cursorId` are non-null:

```sql
(
    occurred_at < :cursor_at
    OR (
        occurred_at = :cursor_at
        AND id < :cursor_id
    )
)
```

Protected ordering:

```sql
ORDER BY occurred_at DESC, id DESC
```

Protected limit behavior:

```php
$limit = max(1, $query->limit);
```

The primitive path currently uses `SELECT *`. That behavior remains protected for the primitive repository. The new Admin Query path must use an explicit selected-column list.

The primitive repository does not begin, commit, or roll back transactions.

### 2.6 Primitive row iteration and hydration

- Rows returned by `fetchAll(PDO::FETCH_ASSOC)` that are not arrays are skipped.
- Numeric `id` maps to `int`; otherwise `0`.
- String `event_id` maps to `eventId`; otherwise `''`.
- String `actor_type` maps to `actorType`; otherwise `null`.
- Numeric `actor_id` maps to `int`; otherwise `null`.
- String `signal_type` maps to `signalType`; otherwise `''`.
- String `severity` maps to `severity`; otherwise `''`.
- String `correlation_id`, `request_id`, `route_name`, `ip_address`, and `user_agent` map to their DTO fields; non-strings map to `null`.
- Missing or non-string `occurred_at` maps from `1970-01-01 00:00:00` in UTC.
- Invalid date text throws during row mapping.
- Missing, non-string, or empty `metadata` maps to `null`.
- Malformed JSON maps to `null`.
- Scalar JSON maps to `null`.
- A JSON array containing any numeric key maps to `null`.
- An associative JSON object maps to `array<string, mixed>`.

### 2.7 Primitive exception boundary

The protected query failure prefix is exactly:

```text
Failed to query SecuritySignals records: 
```

The protected row-mapping failure prefix is exactly:

```text
Failed to map SecuritySignals row: 
```

The primitive repository catches `PDOException` for query execution and `Throwable` for row mapping. The original throwable is preserved as `previous`.

A future shared-mapper refactor must not alter these public messages, catch boundaries, constructor shape, filtering, ordering, cursor behavior, or hydration fallbacks.

---

## 3. SecuritySignals Schema Contract

The exact table is:

```text
maa_event_logging_security_signals
```

### 3.1 Columns

| Column | SQL type | Nullability |
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

`actor_type`, `signal_type`, `severity`, `correlation_id`, and `request_id` are leftmost indexed dimensions. `actor_id` without `actor_type` cannot use the leftmost prefix of `idx_el_security_signals_actor_time` and may require a wider scan.

The table is non-authoritative and best-effort. Its schema states that SecuritySignals failures must not affect control flow. `metadata` must not contain passwords, OTP codes, tokens, or other secrets.

---

## 4. Write Policy and Reliability Boundary

`SecuritySignalsPolicyInterface` belongs to the write/recording path. It:

- normalizes actor type;
- normalizes severity;
- validates metadata JSON size.

`SecuritySignalsDefaultPolicy` falls back to:

- `ANONYMOUS` for invalid actor type;
- `INFO` for invalid severity;
- a maximum metadata JSON size of `65535` bytes.

`SecuritySignalsRecorder` catches `Throwable` across command construction, policy normalization, metadata processing, record DTO construction, writer execution, and fallback logging. Recording is fail-open.

The primitive query repository does not use `SecuritySignalsPolicyInterface`. Therefore, the proposed shared read mapper is **policy-free**:

- it must not normalize stored actor type;
- it must not normalize stored severity;
- it must not invoke metadata-size policy;
- it must preserve the current persisted-row fallback behavior exactly.

Admin Query failures are not fail-open. Direct query usage must expose the approved validation, execution, or storage exception boundary.

---

## 5. Superseded Post-v1 Pagination Experiment

PR #74 introduced exactly these four Runtime artifacts:

```text
src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php
src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php
src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php
src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php
```

It also introduced exactly these three tests:

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
- the service depends on the protected primitive `SecuritySignalsQueryInterface`;
- the interface references `SecuritySignalsQueryDTO` and `SecuritySignalsQueryPageDTO`;
- the page DTO references `SecuritySignalsQueryCursorDTO` and `SecuritySignalsViewDTO`;
- the three unit tests directly exercise the wrapper artifacts;
- current package reference, architecture, roadmap, and compatibility documents describe the artifact family or its remediation status.

No current default factory, provider, bootstrap binding, or package example is authorized to make this wrapper the target Admin Query architecture.

External host-consumer absence has not been proven by this repository audit. Every host repository must be searched before deletion.

No superseded artifact may be deleted by this documentation PR. Retirement is atomic with the approved replacement Runtime and its complete compatibility gate.

---

## 6. Proposed Public Admin Query Interface

The future public interface is proposed as:

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

The method name is `paginate()`, matching the package-owned AuditTrail and BehaviorTrace Admin Query contracts.

No `maatify/persistence` type appears in this public interface.

---

## 7. Proposed Request DTO Contract

The future request DTO is proposed as:

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
        // Exact normalization and validation behavior is defined below.
        // No Runtime implementation is authorized by this example.
    }
}
```

Exact serialized keys and order:

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

`after` and `before` serialize with `DATE_ATOM`.

### 7.1 String normalization

For `actorType`, `signalType`, `severity`, `requestId`, `correlationId`, `sortBy`, and `sortDirection`:

1. `null` remains `null`;
2. trim the string;
3. an empty trimmed string becomes `null`;
4. validate UTF-8 without requiring `ext-mbstring`, using the established `/./us` `preg_match_all` pattern;
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

### 7.2 ID and date validation

- `actorId`, when non-null, must be greater than zero.
- If both dates are supplied, `after` must be less than or equal to `before`.
- Equal boundaries are valid.
- `page` and `perPage` remain `int|string|null`; normalization, clamping, and offset calculation are delegated to `maatify/persistence`.

### 7.3 Sorting normalization

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

### 7.4 Unresolved actor pair decision

The following decision remains blocked for explicit Owner approval:

```text
Should actorId require actorType in the new SecuritySignals Admin Query request?
```

Two possible contracts exist:

1. **Require the pair:** reject `actorId` when `actorType` is `null`, matching the composite-index prefix and the established AuditTrail/BehaviorTrace Admin pattern.
2. **Allow standalone actorId:** preserve the primitive filter freedom while accepting potentially wider scans.

`actorType` without `actorId` remains valid under either option.

No Runtime implementation may choose between these options before Owner approval.

---

## 8. Proposed Page Result DTO Contract

The future result DTO is proposed as:

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

## 9. Proposed Runtime Components and Visibility

### 9.1 Public infrastructure adapter

```text
src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsAdminQueryMysqlRepository.php
```

It implements `SecuritySignalsAdminQueryInterface` and is a public package infrastructure adapter.

Proposed constructor:

```php
public function __construct(private PDO $pdo)
```

The constructor accepts only `PDO`. The mapper, descriptor builder, paginator, and pagination configuration are created internally. No injectable paginator or production testing seam is authorized.

### 9.2 Internal implementation details

```text
src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsRowMapper.php

src/SecuritySignals/Infrastructure/Mysql/Pagination/
SecuritySignalsAdminQueryDescriptorBuilder.php
```

Both are marked `@internal`.

### 9.3 Policy-free row mapper

Proposed mapper contract:

```php
/** @internal */
final class SecuritySignalsRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): SecuritySignalsViewDTO
    {
        // Implementation must reproduce the protected primitive mapping exactly.
    }
}
```

The mapper has no policy constructor and applies no read-time normalization.

The future primitive repository may replace its private mapping helpers with this mapper only when regression tests prove identical behavior.

---

## 10. Exact SQL and Descriptor Contract

### 10.1 Shared filter source

The internal descriptor builder must generate one shared structure:

```php
array{
    whereSql: string,
    params: array<string, string|int|bool|null>
}
```

The same `whereSql` and `params` are used by filtered-count and data queries.

Parameter-array keys do not include leading colons.

Accepted Admin filters map exactly as follows:

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

Dates are converted to UTC and formatted as:

```text
Y-m-d H:i:s.u
```

`DATE_ATOM` is only for DTO JSON serialization.

### 10.2 Total count SQL

```sql
SELECT COUNT(*)
FROM maa_event_logging_security_signals
```

There are currently no mandatory SecuritySignals row-visibility constraints inside the package. Therefore, `total` counts all rows in the table.

### 10.3 Filtered count SQL

```sql
SELECT COUNT(*)
FROM maa_event_logging_security_signals
{whereSql}
```

### 10.4 Data SQL

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

The descriptor data SQL must not contain:

```text
ORDER BY
LIMIT
OFFSET
```

Those mechanics belong to `maatify/persistence`.

### 10.5 Unsupported Admin filters

The proposed request does not support:

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

These fields remain available in returned `SecuritySignalsViewDTO` items where applicable, but are not package-level Admin filter inputs.

---

## 11. Pagination Configuration

The package delegates generic page mechanics to `maatify/persistence ^1.1.0`.

Canonical configuration:

```text
public sort key:        occurred_at

internal sort mapping:
    occurred_at -> occurred_at
    id          -> id

default sort:           occurred_at DESC
tie-breaker:            id DESC
default per page:       20
minimum per page:       1
maximum per page:       200
```

The public EventLogging interface and DTOs expose no `maatify/persistence` classes.

`total`, `filtered`, data ordering, page normalization, per-page clamping, offset calculation, `LIMIT`, `OFFSET`, and canonical pagination metadata are produced through the persistence package.

---

## 12. Proposed Repository Execution and Exception Boundary

The Admin repository:

- creates `PageRequest` from request pagination fields;
- builds the SecuritySignals descriptor;
- creates the canonical pagination configuration;
- invokes `PdoPaginator`;
- maps each row through `SecuritySignalsRowMapper`;
- returns `SecuritySignalsAdminPageResultDTO`;
- owns no transaction.

Storage execution failures:

```text
PaginationExecutionException
PDOException
```

translate to `SecuritySignalsStorageException` using:

```text
Failed to query SecuritySignals records: {original message}
```

Pagination configuration or descriptor validation failures:

```text
InvalidPaginationConfigurationException
InvalidPaginationQueryException
```

translate to `SecuritySignalsAdminQueryExecutionException`.

A mapper `Throwable` translates to `SecuritySignalsStorageException` using:

```text
Failed to map SecuritySignals row: {original message}
```

A `SecuritySignalsStorageException` thrown by the mapping callback must propagate unchanged. It must not be rewrapped as a paginator execution failure.

---

## 13. Proposed Exception Classes and Exact Messages

### 13.1 Invalid argument exception

Proposed class:

```text
SecuritySignalsAdminQueryInvalidArgumentException
```

It:

- extends `Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException`;
- implements `Maatify\EventLogging\Exception\EventLoggingExceptionInterface`.

Exact proposed factories and messages:

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

### 13.2 Execution exception

Proposed class:

```text
SecuritySignalsAdminQueryExecutionException
```

It:

- extends `SystemMaatifyException`;
- implements `EventLoggingExceptionInterface`;
- preserves the previous throwable;
- uses `ErrorCodeEnum::MAATIFY_ERROR`.

Exact proposed message:

```text
SecuritySignals Admin Query execution failed: {original message}
```

### 13.3 Storage exception

The existing `SecuritySignalsStorageException` remains the storage boundary. Its class, error code, and primitive behavior are not redesigned.

---

## 14. Native PDO Primitive Cursor Issue

The current primitive SQL reuses the named placeholder `:cursor_at` twice. Native MySQL prepared statements may reject repeated named placeholders.

Proposed behavior-preserving future correction:

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

This correction:

- does not change the public primitive interface;
- does not change cursor activation;
- does not change ordering;
- does not change page semantics;
- is not implemented by PR #102;
- requires explicit Owner approval;
- requires focused unit, regression, and real MySQL native-prepared-statement coverage.

---

## 15. Future Test and Compatibility Matrix

### 15.1 Request DTO unit tests

- exact constructor field order and defaults;
- exact serialized key order;
- `DATE_ATOM` serialization;
- nullable values;
- trimming;
- empty-string normalization;
- UTF-8 validation without `ext-mbstring`;
- every maximum length boundary;
- over-limit values;
- positive `actorId`;
- zero and negative `actorId`;
- equal date boundaries;
- invalid date order;
- page/per-page passthrough;
- valid and invalid public sort;
- case-insensitive direction normalization;
- whichever actor pair rule is Owner-approved.

### 15.2 Result DTO unit tests

- constructor values;
- exact serialized root keys;
- iterator behavior;
- empty items;
- canonical pagination metadata.

### 15.3 Row mapper unit tests

- every field mapped normally;
- every numeric fallback;
- every string fallback;
- nullable context fields;
- missing/non-string date fallback;
- invalid date failure;
- associative metadata;
- missing metadata;
- empty metadata;
- malformed JSON;
- scalar JSON;
- numeric-key JSON array;
- mapper has no policy dependency.

### 15.4 Descriptor and pagination unit tests

- total SQL exactly matches the table-only count;
- explicit 13-column data selection;
- no `SELECT *`;
- no `ORDER BY`, `LIMIT`, or `OFFSET` in descriptor SQL;
- every filter individually;
- combined filters;
- exact parameter names and values;
- UTC date conversion;
- equal/inclusive date boundaries;
- filtered-count/data `WHERE` and parameter identity;
- public and internal sort whitelist behavior;
- default and tie-breaker directions;
- default/min/max per-page configuration.

### 15.5 Repository unit tests

- `PDO`-only constructor;
- page/result mapping;
- no transaction ownership;
- storage translation for PDO failures;
- storage translation for pagination execution failures;
- execution translation for invalid configuration/query descriptors;
- row-mapping prefix;
- previous throwable preservation;
- mapper storage exception propagation without rewrapping.

### 15.6 Primitive regression tests

- `SecuritySignalsQueryInterface::find()` signature;
- primitive DTO constructor order, defaults, and serialization;
- `SecuritySignalsViewDTO` constructor and serialization;
- primitive repository constructor;
- every primitive filter;
- cursor condition only when both cursor values exist;
- `occurred_at DESC, id DESC`;
- `max(1, limit)`;
- non-array row skipping;
- all hydration fallbacks;
- all JSON behaviors;
- exact query and mapping message prefixes;
- no transaction ownership;
- write-side policy behavior unchanged;
- recorder fail-open behavior unchanged;
- superseded wrapper artifacts retained until the approved replacement gate.

### 15.7 Real MySQL integration tests

- a live MySQL server only;
- no SQLite fallback;
- native prepared statements;
- missing DSN or connection failure must fail the strict Admin/primitive integration gate rather than silently count as success;
- every Admin filter separately;
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
- primitive cursor correction;
- primitive/Admin semantic alignment;
- repository does not own the caller transaction.

### 15.8 Package gates

```text
composer validate --strict
composer analyse
composer test:unit
composer test:regression
real MySQL integration suite
git diff --check
```

A skipped integration job is not a passing integration result.

---

## 16. Exact Future File Inventory

### 16.1 Future additions

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

### 16.2 Future modifications

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

The primitive repository modification is restricted to shared mapper extraction and the separately approved placeholder correction, with full regression coverage.

### 16.3 Future deletions after replacement gate

```text
src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php
src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php
src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php
src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php
tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php
```

Deletion is not authorized until replacement Runtime, unit tests, regression tests, strict real MySQL integration tests, static analysis, package validation, and host-consumer searches have passed.

### 16.4 Protected unchanged behavior

- primitive public interface and method signature;
- primitive DTO public constructors and serialization;
- primitive repository `PDO`-only constructor;
- primitive filters, cursor semantics, ordering, and limit behavior;
- primitive hydration and exception prefixes;
- schema;
- writer behavior;
- policy behavior;
- recorder fail-open boundary;
- factory/provider/bootstrap behavior unless a separately approved public Admin binding decision is made.

### 16.5 Explicitly out of scope

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
host application wiring
tagging
release publication
```

---

## 17. Owner Decisions and External Gates

The following remain unresolved and block Runtime authorization:

1. **Actor pair rule:** whether `actorId` requires `actorType`.
2. **Primitive placeholder correction:** whether the future Runtime PR is authorized to replace the repeated `:cursor_at` placeholder with `:cursor_at_before` and `:cursor_at_equal`.
3. **External consumers:** host repositories must be searched before removing the seven superseded Runtime/test artifacts.

The following proposals are explicit in this blueprint but are not Owner-approved until a separate approval action:

- policy-free shared row mapper;
- public `paginate()` contract;
- public sorting limited to `occurred_at`;
- invalid short sort values normalize to `null`;
- `PDO`-only Admin repository constructor;
- exact exception names and messages;
- strict non-skipping real MySQL gates;
- exact future file inventory.

---

## 18. Runtime Authorization Gate

No SecuritySignals Runtime implementation is authorized by this blueprint or by PR #102.

Progression requires:

1. review and correction of PR #102;
2. explicit Owner decisions on the unresolved items;
3. an Owner-approval documentation update or separate approval PR;
4. a separate Runtime task assigned to Codex;
5. complete Unit, Regression, strict real MySQL Integration, PHPStan, Composer, and architecture-boundary gates.

Until those steps occur, the status remains:

```text
Proposed / Runtime Implementation Blocked
```
