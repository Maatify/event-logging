# Blueprint Drafted / Pending Owner Approval

## 1. Audit the Current Main State

* **Exact audited main SHA:** `59ac1afee2313172d11de4f008169c5fd9c824a6`
* **Exact maatify/persistence Composer constraint currently installed:** `^1.1.0`
* **Current package exception marker state:** `Maatify\Exceptions\Exception\System\SystemMaatifyException`
* **AuditTrail POC merge commit:** `59ac1afee2313172d11de4f008169c5fd9c824a6` (PR #98)
* **Exact current BehaviorTrace Runtime and test inventory:** Tested via integration tests and unit tests. Has paginated wrapper artifacts. Primitive query repository exists with `find` and `read` methods.

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

* filtered primitive query;
* descending order: `occurred_at DESC, id DESC`;
* primitive cursor fields: `cursorOccurredAt`, `cursorId`;
* current limit normalization;
* current storage and mapper exception messages.

### `read()`

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

### Row Mapper Target

Target: `BehaviorTraceRowMapper`

The mapper must receive the effective `BehaviorTracePolicyInterface` and be shared by:
* the primitive repository;
* the future Admin Query repository.

The blueprint defines exact construction without introducing a generic mapper, service locator, container dependency, or framework binding.

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

These are not protected `v1.0.0` primitive contracts.
They must not be deleted until the replacement Runtime passes its complete compatibility gate.

## 5. Define the Separate Public Admin Query API

The future public interface:

```php
Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceAdminQueryInterface
```

Exact method shape:

```php
public function paginate(
    BehaviorTraceAdminQueryRequestDTO $request
): BehaviorTraceAdminPageResultDTO;
```

The public API must not expose any `maatify/persistence` class.

### Expected Filter Surface

* actorType
* actorId
* action
* entityType
* entityId
* requestId
* correlationId
* after
* before
* page
* perPage
* sortBy
* sortDirection

Validation rules include:
* trim nullable strings;
* whitespace-only strings become `null`;
* IDs must be positive;
* `actorId` without `actorType` is invalid;
* `entityId` without `entityType` is invalid;
* type-only filters are valid;
* `after > before` is invalid;
* equal boundaries are valid;
* page and per-page remain raw `int|string|null`;
* caller-selectable sort is limited to `occurred_at`;
* `id` is internal tie-breaker only;
* sort direction supports `ASC` and `DESC`;
* invalid short sort values normalize to `null`;
* overlong values throw the package validation exception based on exact max schema lengths:
  * `actorType`: 32 chars max;
  * `action`: 128 chars max;
  * `entityType`: 64 chars max;
  * `requestId`: 64 chars max;
  * `correlationId`: 36 chars max;
* UTF-8 validation must not require `ext-mbstring`;
* DTO JSON dates use `DATE_ATOM`;
* database timestamp formatting belongs in the SQL descriptor builder.

## 6. Define Pagination and SQL Architecture

The future implementation must use the already-installed `maatify/persistence ^1.1.0`.

Expected components:
* `BehaviorTraceAdminQueryMysqlRepository`
* `BehaviorTraceAdminQueryDescriptorBuilder`
* `BehaviorTraceRowMapper`

Exact table: `maa_event_logging_behavior_trace`

The blueprint defines:
* total count SQL;
* filtered count SQL;
* data SQL;
* one shared condition and parameter source for filtered count and data;
* explicit selected-column list;
* no `SELECT *` in Admin Query SQL;
* no `ORDER BY`, `LIMIT`, or `OFFSET` inside the descriptor data SQL;
* UTC conversion for date parameters;
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

## 7. Define Policy-Aware Row Hydration

Exact BehaviorTrace row hydration rules for:
`id`, `event_id`, `action`, `entity_type`, `entity_id`, `actor_type`, `actor_id`, `correlation_id`, `request_id`, `route_name`, `ip_address`, `user_agent`, `metadata`, `occurred_at`.

It must preserve:
* actor normalization through the effective `BehaviorTracePolicyInterface`;
* current default values;
* nullable fields;
* invalid metadata JSON mapping;
* timestamp fallback or throwable behavior;
* custom policy behavior;
* `BehaviorTraceContextDTO` construction;
* `BehaviorTraceEventDTO` construction.

**Decision for mapper/policy failures:** The primitive repository catches `\Exception` around the mapping process and translates it to `BehaviorTraceStorageException`. The future shared mapper must throw its raw `\Exception` on failure. The `BehaviorTraceAdminQueryMysqlRepository` must catch this exception and translate it to `BehaviorTraceStorageException`, explicitly preserving the original exception as the previous throwable, matching exactly the primitive boundary's translation strategy.

## 8. Define Exception Architecture

Future exceptions:
* `BehaviorTraceAdminQueryInvalidArgumentException`
* `BehaviorTraceAdminQueryExecutionException`

Requirements:
* validation exception extends the approved Maatify invalid-argument exception;
* execution exception extends the approved Maatify system exception;
* both implement `EventLoggingExceptionInterface`;
* PDO and persistence execution failures translate to `BehaviorTraceStorageException`;
* persistence configuration/query construction failures translate to the Admin Query execution exception;
* previous throwables are preserved;
* no generic `RuntimeException`;
* no generic `Throwable` catch unless current BehaviorTrace policy/hydration behavior proves it is required and the blueprint documents the exact reason.

## 9. Primitive Cursor Compatibility Hazard

**Verdict:** Behavior-Preserving Primitive Compatibility Correction — Pending Owner Approval

Currently, `find()` uses the `cursor_at` placeholder in two places within the `OR` clause. Native prepared statements in MySQL strictly require unique placeholders when reusing values, meaning separate placeholders such as `cursor_at_before` and `cursor_at_equal` are required.

*   **Current behavior:** The query currently relies on PDO emulation which allows placeholder reuse.
*   **MySQL native-prepared-statement impact:** Disabling emulation breaks the current `cursor_at` reuse, causing PDO to throw an exception.
*   **Semantic impact:** There is absolutely no change to the query semantics or logic.
*   **Backward-compatibility impact:** None. The API surface, query results, and external behavior remain exactly identical.
*   **Required regression and integration evidence:** Real MySQL integration tests for `find()` must verify the correct descending cursor output using native prepared statements to prove that splitting the placeholder preserves the identical results.

## 10. Exact Future Runtime File Inventory

**Create:**
* `src/BehaviorTrace/Contract/BehaviorTraceAdminQueryInterface.php`
* `src/BehaviorTrace/DTO/BehaviorTraceAdminPageResultDTO.php`
* `src/BehaviorTrace/DTO/BehaviorTraceAdminQueryRequestDTO.php`
* `src/BehaviorTrace/Exception/BehaviorTraceAdminQueryExecutionException.php`
* `src/BehaviorTrace/Exception/BehaviorTraceAdminQueryInvalidArgumentException.php`
* `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceAdminQueryDescriptorBuilder.php`
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
* Unit tests for the new DTOs, Repository, Descriptor Builder, and Exceptions.
* Integration tests for AdminQuery API.

**Tests to delete:**
* `tests/Unit/BehaviorTrace/Service/BehaviorTracePaginatedQueryServiceTest.php`
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceQueryCursorDTOTest.php`
* `tests/Unit/BehaviorTrace/DTO/BehaviorTraceQueryPageDTOTest.php`

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
Prove:
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
* primitive `find()` remains functional;
* primitive `read()` remains functional;
* custom policy behavior remains functional;
* new integration tests are not skipped.

## Status and Approval Gate

- [ ] I confirm no Runtime implementation is authorized.
- [ ] I confirm no artifact deletion is authorized.
- [ ] I confirm no Composer change is required or authorized.
- [ ] I confirm no schema change is authorized.
- [ ] I confirm no SecuritySignals or AuthoritativeAudit work is authorized.
- [ ] I confirm no tag or release is authorized.
- [ ] I approve the blueprint and every explicit compatibility decision, unblocking the Runtime implementation.
