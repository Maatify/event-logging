# SecuritySignals Admin Query Rebuild Blueprint

**Status:** Proposed / Runtime Implementation Blocked

## A. Audited Baseline

* **Audit Date:** 2025-02-12 (UTC)
* **Starting SHA:** 3169947e107df66f61884abb5c95f1dfa621a69b
* **PR Branch HEAD before correction:** d5121cee3a3069aaaaea5dded2521ae1316f5fdb
* **Sources Reviewed:**
  * `src/SecuritySignals/Contract/*`
  * `src/SecuritySignals/DTO/*`
  * `src/SecuritySignals/Infrastructure/Mysql/*`
  * `src/SecuritySignals/Service/*`
* **Tests Reviewed:**
  * `tests/Unit/SecuritySignals/Repository/SecuritySignalsQueryMysqlRepositoryTest.php`
  * `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php`
  * `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php`
  * `tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php`
* **Schema Reviewed:** `src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql`
* **Historical Origins:** PR #74
* **Governing Documents:**
  * `EVENT_LOGGING_PACKAGE_REFERENCE.md`
  * `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md`
  * `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`

**Explicit separation:**
* **Protected `v1.0.0` Runtime:** The primitive interfaces and DTOs that MUST NOT be modified.
* **Superseded post-v1 pagination experiment:** The paginated interfaces, DTOs, and services that MUST be retired.
* **Proposed Admin Query architecture:** The exact future contracts defined in this blueprint.

## B. Protected Primitive Contracts

### Interface
```php
public function find(SecuritySignalsQueryDTO $query): array;
```

### Query DTO (`SecuritySignalsQueryDTO`)
Constructor order and defaults:
* `after = null`
* `before = null`
* `actorType = null`
* `actorId = null`
* `signalType = null`
* `severity = null`
* `requestId = null`
* `correlationId = null`
* `cursorOccurredAt = null`
* `cursorId = null`
* `limit = 50`

`jsonSerialize()` keys: `after`, `before`, `actorType`, `actorId`, `signalType`, `severity`, `requestId`, `correlationId`, `cursorOccurredAt`, `cursorId`, `limit`.

### View DTO (`SecuritySignalsViewDTO`)
Constructor and serialization fields:
* `id`
* `eventId`
* `actorType`
* `actorId`
* `signalType`
* `severity`
* `correlationId`
* `requestId`
* `routeName`
* `ipAddress`
* `userAgent`
* `metadata`
* `occurredAt`

### Repository (`SecuritySignalsQueryMysqlRepository`)
* **Constructor:** `public function __construct(private readonly PDO $pdo)`
* **Conditions:** Requires both `cursorOccurredAt` and `cursorId` to activate the descending cursor filter: `(occurred_at < :cursor_at OR (occurred_at = :cursor_at AND id < :cursor_id))`
* **Limit behavior:** `max(1, $query->limit)`
* **Ordering:** `ORDER BY occurred_at DESC, id DESC`
* **Transaction:** The repository does not own or manage transactions.
* **Skipped rows:** Non-array fetched rows are explicitly skipped via `!is_array($row)`.
* **Exact filters:** Time bounds, `actorType`, `actorId`, `signalType`, `severity`, `requestId`, `correlationId`. Date parameters format as `Y-m-d H:i:s.u`.
* **Exception boundary:** Catch blocks are strictly `PDOException` and `Throwable`.
  * `PDOException`: throws `SecuritySignalsStorageException('Failed to query SecuritySignals records: ' . $e->getMessage(), 0, $e)`
  * `Throwable`: throws `SecuritySignalsStorageException('Failed to map SecuritySignals row: ' . $e->getMessage(), 0, $e)`

### Hydration Behavior Fallbacks
* numeric `id` to integer; otherwise `0`
* string `eventId`; otherwise empty string `''`
* string `actor_type`; otherwise `null`
* numeric `actor_id`; otherwise `null`
* string `signal_type`; otherwise empty string `''`
* string `severity`; otherwise empty string `''`
* optional string context fields (`correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`); otherwise `null`
* missing or non-string date uses Unix epoch UTC (`1970-01-01 00:00:00`)
* invalid date text throws during mapping
* missing, empty, malformed, scalar, or non-associative JSON metadata maps to `null`
* associative JSON object maps to `array<string,mixed>`

## C. Superseded Post-v1 Artifacts

Introduced exactly in historical PR #74, these artifacts are classified as **Superseded Post-v1 Experiment**:

* `src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php`
* `src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php`
* `src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php`
* `src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php`

And exactly these tests:
* `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php`
* `tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php`
* `tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php`

No known package-internal references exist. Host consumer references must be searched by consumers before final deletion. Deletion is NOT authorized in PR #102.

## D. Domain Schema Requirements

Table name: `maa_event_logging_security_signals`

**Columns:**
1. `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
2. `event_id` CHAR(36) NOT NULL (UNIQUE KEY)
3. `actor_type` VARCHAR(32) NOT NULL
4. `actor_id` BIGINT NULL
5. `signal_type` VARCHAR(100) NOT NULL
6. `severity` VARCHAR(16) NOT NULL
7. `correlation_id` CHAR(36) NULL
8. `request_id` VARCHAR(64) NULL
9. `route_name` VARCHAR(255) NULL
10. `ip_address` VARCHAR(45) NULL
11. `user_agent` VARCHAR(512) NULL
12. `metadata` JSON NOT NULL (Prohibited from containing secrets)
13. `occurred_at` DATETIME(6) NOT NULL

**Indexes:**
* `idx_el_security_signals_time (occurred_at, id)`
* `idx_el_security_signals_actor_time (actor_type, actor_id, occurred_at)`
* `idx_el_security_signals_type_time (signal_type, occurred_at)`
* `idx_el_security_signals_severity_time (severity, occurred_at)`
* `idx_el_security_signals_corr_time (correlation_id, occurred_at)`
* `idx_el_security_signals_request_time (request_id, occurred_at)`

**Risk:** Filtering on `actorId` without `actorType` would bypass the leftmost prefix of `idx_el_security_signals_actor_time`, resulting in full table scans.

**Boundary:** The table is non-authoritative and best-effort; failures here MUST NOT block user actions. The recorder fail-open boundary MUST be maintained.

## E. Proposed Public Contract

```php
public function paginate(
    SecuritySignalsAdminQueryRequestDTO $request
): SecuritySignalsAdminPageResultDTO;
```

**Exceptions:**
* `SecuritySignalsAdminQueryInvalidArgumentException`
* `SecuritySignalsAdminQueryExecutionException`
* `SecuritySignalsStorageException`

## F. Complete Request DTO

**`SecuritySignalsAdminQueryRequestDTO`** (final readonly, implements `JsonSerializable`)

Fields (in exact constructor order):
* `actorType`
* `actorId`
* `signalType`
* `severity`
* `requestId`
* `correlationId`
* `after`
* `before`
* `page`
* `perPage`
* `sortBy`
* `sortDirection`

**Validation and Normalization rules:**
* Trim nullable strings. Empty strings normalize to `null`.
* Validate UTF-8 without requiring `ext-mbstring`.
* `actorType` max 32.
* `signalType` max 100.
* `severity` max 16.
* `requestId` max 64.
* `correlationId` max 36.
* `actorId`, when provided, must be positive.
* `after` must be before or equal to `before` (equal boundaries valid).
* `page` and `perPage` remain `int|string|null` (delegated to persistence).
* `sortBy` is strictly `occurred_at`. Short unsupported sort values normalize to `null`. Overlong/invalid UTF-8 throws a validation exception. `id` is NOT a public sort key.
* `sortDirection` accepts `ASC` or `DESC` case-insensitively. Short unsupported directions normalize to `null`.

**Unresolved Decision:** Should `actorId` strictly require `actorType` in the Admin Query contract?

## G. Complete Page Result DTO

**`SecuritySignalsAdminPageResultDTO`** (final readonly, implements `IteratorAggregate<int, SecuritySignalsViewDTO>`, `JsonSerializable`)

Constructor and serialization fields:
* `items`
* `page`
* `perPage`
* `total`
* `filtered`
* `totalPages`
* `hasNext`
* `hasPrevious`
* `sortBy`
* `sortDirection`

## H. Runtime File Paths and Visibility

**Public infrastructure:**
* `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsAdminQueryMysqlRepository.php`
  * Constructor strictly accepts `PDO $pdo`. No policy, paginator, or builder is publicly injected.

**Internal details (`@internal`):**
* `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsRowMapper.php`
* `src/SecuritySignals/Infrastructure/Mysql/Pagination/SecuritySignalsAdminQueryDescriptorBuilder.php`

## I. Policy-Free Row Mapper

The shared static row mapper must be proposed as **policy-free**. Write-side policy normalization (`SecuritySignalsPolicyInterface`) belongs strictly to the recording path.

* No policy injection in the mapper.
* No normalization of stored actor type or severity during reads.
* Malformed persisted values retain current read fallback behavior.
* Mapper exceptions translate exactly using the prefix: `Failed to map SecuritySignals row: `

## J. Exact SQL and Descriptor Architecture

* **Total Count:** `SELECT COUNT(*) FROM maa_event_logging_security_signals` (No optional filters).
* **Filtered Count:** Uses the exact same `WHERE` generated by the shared descriptor logic.
* **Data Query:** `SELECT id, event_id, actor_type, actor_id, signal_type, severity, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at` (13 columns, NO `SELECT *`).
* **Paginator mechanics:** `ORDER BY`, `LIMIT`, and `OFFSET` MUST NOT be in the descriptor data SQL.
* **Shared Builder:** One shared method produces `whereSql` and `params` for both filtered and data queries.
* **Parameters:** Use keys without leading colons.
* **Date format:** Convert to UTC and format as `Y-m-d H:i:s.u`.

**Filter Mappings:**
* `actorType` -> `actor_type =`
* `actorId` -> `actor_id =`
* `signalType` -> `signal_type =`
* `severity` -> `severity =`
* `requestId` -> `request_id =`
* `correlationId` -> `correlation_id =`
* `after` -> `occurred_at >=`
* `before` -> `occurred_at <=`

**Explicitly Unsupported:** `eventId`, `routeName`, `ipAddress`, `userAgent`, `metadata`, free-text search, arbitrary SQL sort keys.

## K. Pagination Configuration Precisely

* **Public whitelist:** `occurred_at`
* **Internal whitelist:** `occurred_at` -> `occurred_at`, `id` -> `id`
* **Config:**
  * `defaultSortBy`: `occurred_at`
  * `defaultSortDirection`: `DESC`
  * `tieBreakerSortBy`: `id`
  * `tieBreakerDirection`: `DESC`
  * `defaultPerPage`: 20
  * `minPerPage`: 1
  * `maxPerPage`: 200

## L. Exception Architecture

* **`SecuritySignalsAdminQueryInvalidArgumentException`** (Validation): Extends `InvalidArgumentMaatifyException`, implements `EventLoggingExceptionInterface`.
  * Factories/messages for: invalid ID, invalid length, invalid UTF-8, invalid date range.
* **`SecuritySignalsAdminQueryExecutionException`** (Execution): Extends `SystemMaatifyException`, implements `EventLoggingExceptionInterface`.
  * Thrown strictly for invalid pagination configuration or descriptor configuration.
* **Storage Translations:** PDO/Paginator execution exceptions translate to `SecuritySignalsStorageException` matching the primitive message (`Failed to query SecuritySignals records: `). Row mapping failures use (`Failed to map SecuritySignals row: `). Do not translate storage failures to ExecutionException.

## M. Primitive Native PDO Placeholder Issue

Primitive queries repeat `:cursor_at`. A behavior-preserving correction is proposed for future Runtime implementation:
```sql
occurred_at < :cursor_at_before
OR (
    occurred_at = :cursor_at_equal
    AND id < :cursor_id
)
```
* NOT authorized in PR #102.
* Requires Owner approval and strict testing with native prepared statements.

## N. Complete Test and Verification Plan

**Unit Matrix:**
* DTO constructor/serialization, trim/empty logic, UTF-8 validity, length bounds, positive actor ID, date range, sort normalization.
* Result DTO iteration/serialization.
* Row mapper fallbacks, JSON metadata edge cases (associative, empty, malformed, scalar, numeric-keys), and invalid date parsing.
* SQL descriptor exact parameter maps.
* Pagination configuration constraints.
* Exception factories and translations.

**Regression Matrix:**
* Primitive interfaces, DTOs, constraints, and repository constructors remain unchanged.
* Both cursor values required to activate descending behavior.
* Deterministic ordering and limit behavior.
* Non-array skipping and fail-open writing.
* Exact query and mapping exception prefixes.

**Integration Matrix (Real MySQL):**
* No SQLite fallback. No silent skips. Native prepared statements enforced.
* Single and combined filters, inclusive/equal date bounds, zero results, multiple pages, clamping.
* Nullable field hydration.

**Package Gates:** `composer validate --strict`, `composer analyse`, `composer test:unit`, `composer test:regression`, real MySQL integration, `git diff --check`.

## O. Future File Inventory

**Future Additions:**
* `src/SecuritySignals/Contract/SecuritySignalsAdminQueryInterface.php`
* `src/SecuritySignals/DTO/SecuritySignalsAdminQueryRequestDTO.php`
* `src/SecuritySignals/DTO/SecuritySignalsAdminPageResultDTO.php`
* `src/SecuritySignals/Exception/SecuritySignalsAdminQueryInvalidArgumentException.php`
* `src/SecuritySignals/Exception/SecuritySignalsAdminQueryExecutionException.php`
* `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsAdminQueryMysqlRepository.php`
* `src/SecuritySignals/Infrastructure/Mysql/SecuritySignalsRowMapper.php`
* `src/SecuritySignals/Infrastructure/Mysql/Pagination/SecuritySignalsAdminQueryDescriptorBuilder.php`

**Future Modifications:**
* `SecuritySignalsQueryMysqlRepository` (shared mapper, placeholder correction)
* `EVENT_LOGGING_PACKAGE_REFERENCE.md`
* `docs/integration/ADMIN_READ_USAGE.md`
* `src/SecuritySignals/README.md`
* `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`
* `docs/audits/DOCUMENTATION_INVENTORY.md`
* `CHANGELOG.md`

**Future Deletions (only after replacement passes):**
* `SecuritySignalsPaginatedQueryInterface.php`
* `SecuritySignalsQueryCursorDTO.php`
* `SecuritySignalsQueryPageDTO.php`
* `SecuritySignalsPaginatedQueryService.php`
* 3 Unit tests exactly associated.

**Explicitly Out of Scope:**
* Schema changes
* Composer dependency changes
* CI workflow changes
* HTTP, controllers, routes, middleware, permissions, UI, localization, exports, dashboards, reporting
* Cross-domain generic repositories
* Host wiring, tagging, release work

## P. Runtime Authorization Gate

No SecuritySignals Runtime implementation is authorized by this blueprint.
Proceeding requires Owner review and a separate Runtime execution task.
