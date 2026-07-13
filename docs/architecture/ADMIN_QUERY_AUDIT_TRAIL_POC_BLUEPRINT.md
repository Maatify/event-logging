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
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryException;

interface AuditTrailAdminQueryInterface
{
    /**
     * @throws AuditTrailAdminQueryException
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
* **Default page:** 1
* **Default per-page:** 25
* **Minimum per-page:** 1
* **Maximum per-page:** 1000
* **Approved public sort keys:** `['occurred_at']`
* **Public sort key to trusted identifier mapping:** `['occurred_at' => 'occurred_at']`
* **Default sort key:** `occurred_at`
* **Default sort direction:** `DESC`
* **Tie-breaker key:** `id`
* **Tie-breaker direction:** `DESC`

**Sort Key Justification:** `occurred_at` is the only supported sort key for the POC as it maps to the canonical timestamp of the audit event and is indexed effectively (`idx_el_audit_trail_time`, `idx_el_audit_trail_actor_time`, etc.). The tie-breaker `id` is required because multiple events can share the exact same microsecond timestamp.

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
* **Visibility:** `internal`
* **Constructor:** None required (static factory or standard instantiation).
* **Methods:** `public function build(AuditTrailAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor;`
* **Shape:** Returns a configured `Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor`.

**Explicit Columns:**
No `SELECT *`. The `dataSql` explicitly selects:
`id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at`

**Parameter Contract:**
* No leading colons.
* Named placeholders only.
* Unique per SQL statement.
* Values map strictly to: `string|int|bool|null`. Dates are stringified. No `DateTimeImmutable` passed directly to persistence.

## 5. Mapper Extraction Strategy

**Path:** `src/AuditTrail/Infrastructure/Mysql/AuditTrailRowMapper.php`
**Namespace:** `Maatify\EventLogging\AuditTrail\Infrastructure\Mysql`
**Modifier:** `internal final`
**Method:** `public function map(array $row): AuditTrailViewDTO`

Constructed manually within `AuditTrailQueryMysqlRepository` and the new `AuditTrailAdminQueryMysqlRepository`.
No factories or bindings require updates.
The extraction guarantees 100% hydration compatibility.

## 6. Domain-Owned Result Adaptation

**Class:** `AuditTrailAdminPageResultDTO`
**Namespace:** `Maatify\EventLogging\AuditTrail\DTO`
**Path:** `src/AuditTrail/DTO/AuditTrailAdminPageResultDTO.php`
**Modifiers:** `final readonly`
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
    )
```

JSON keys strictly match the property names (`items`, not `data`).

## 7. Request DTO Contract

**Class:** `AuditTrailAdminQueryRequestDTO`
**Namespace:** `Maatify\EventLogging\AuditTrail\DTO`
**Path:** `src/AuditTrail/DTO/AuditTrailAdminQueryRequestDTO.php`
**Modifiers:** `final readonly`

**Constructor Signature:**
```php
    public function __construct(
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $eventKey = null,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?string $requestId = null,
        public ?string $correlationId = null,
        public ?\DateTimeImmutable $after = null,
        public ?\DateTimeImmutable $before = null,
        public ?int $page = null,
        public ?int $perPage = null,
        public ?string $sortBy = null,
        public ?string $sortDirection = null
    )
```

Construct-time validation is performed immediately. Throws `AuditTrailAdminQueryException` on violations. No validators delegated. Raw input values are normalized during construction (e.g., whitespace trim).

## 8. Exception Boundary

**Exception Class:** `AuditTrailAdminQueryException`
**Namespace:** `Maatify\EventLogging\AuditTrail\Exception`
**Inheritance:** extends `Maatify\Exceptions\Exception\System\SystemMaatifyException`
**Error Code Policy:** returns `ErrorCodeEnum::DATABASE_CONNECTION_FAILED`

**Named Constructors:**
* `invalidFilterCombination(string $message)`
* `invalidDateRange()`
* `invalidId(string $field)`
* `executionFailed(\Throwable $prev)`

The original throwable is preserved as the previous exception. Nothing is swallowed or ignored. Does not initiate or wrap transactions.

## 9. Dependency Injection and Construction

No modifications to `EventLoggingProvider`, `EventLoggingBindings`, or factories are permitted in this blueprint PR. The new components are strictly designed to be wired independently.

## 10. Exact Runtime File Plan

| Class/File | Action | Responsibility | Public API/Compat Impact |
| --- | --- | --- | --- |
| `AuditTrailAdminQueryInterface.php` | NEW | Admin public contract | None (Additive) |
| `AuditTrailAdminQueryRequestDTO.php`| NEW | Request DTO | None (Additive) |
| `AuditTrailAdminPageResultDTO.php` | NEW | Result DTO | None (Additive) |
| `AuditTrailAdminQueryMysqlRepository.php`| NEW | Repository executing API | None (Additive) |
| `AuditTrailAdminQueryDescriptorBuilder.php`| NEW | Builds internal statements| None (Additive) |
| `AuditTrailRowMapper.php` | NEW | Internal row mapping | None (Strictly preserves DTO hydration) |
| `AuditTrailAdminQueryException.php` | NEW | Query boundaries | None (Additive) |
| `AuditTrailQueryInterface.php` | UNCHANGED | Primitive Interface | Protects v1.0 api |
| `AuditTrailQueryDTO.php` | UNCHANGED | Primitive Interface DTO | Protects v1.0 api |
| `AuditTrailViewDTO.php` | UNCHANGED | Internal View DTO | Protects v1.0 api |
| `AuditTrailQueryMysqlRepository.php`| MODIFY | Inject shared mapper | Maintains 100% backwards compatibility |
| `AuditTrailStorageException.php` | UNCHANGED | Exception handling | Protects v1.0 api |
| Write Side / Factories / Providers | UNCHANGED | - | No impact |
| `AuditTrailPaginatedQueryInterface.php`| DELETE | Obsolete architecture | Break for POC users, as intended |
| `AuditTrailQueryCursorDTO.php` | DELETE | Obsolete architecture | Break for POC users, as intended |
| `AuditTrailQueryPageDTO.php` | DELETE | Obsolete architecture | Break for POC users, as intended |
| `AuditTrailPaginatedQueryService.php`| DELETE | Obsolete architecture | Break for POC users, as intended |
| `AuditTrailPaginatedQueryServiceTest.php`| DELETE| Obsolete architecture | - |
| `composer.json` | MODIFY | Add dependency | Additive |
| `composer.lock` | UNCHANGED / REGRESSION-PROTECTED | Should not be present | Protects release strategy |
| `EVENT_LOGGING_PACKAGE_REFERENCE.md` | MODIFY | Document new API | Additive |
| `docs/integration/ADMIN_READ_USAGE.md`| MODIFY | Document new API | Additive |
| `src/AuditTrail/README.md` | MODIFY | Document new API | Additive |
| `CHANGELOG.md` | MODIFY | Record release | Additive |
| `docs/audits/DOCUMENTATION_INVENTORY.md`| MODIFY | Keep inventory updated | Additive |
| `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`| MODIFY | Keep roadmap updated | Additive |
| Unit Tests (multiple files) | NEW | Verify specific unit logic | Additive |
| Regression Tests (multiple files)| NEW | Verify protection | Additive |
| Integration Tests (multiple files)| NEW | Verify SQL query | Additive |



## 11. Atomic Retirement Sequence

The implementation PR must follow this exact sequence:
1. add `maatify/persistence ^1.1.0`;
2. add domain Admin Query contracts;
3. add filter and descriptor construction;
4. extract shared row mapping;
5. add Admin Query execution path;
6. add result adaptation;
7. add exception translation;
8. add complete Unit tests;
9. add complete Regression tests;
10. add real MySQL Integration tests;
11. prove primitive cursor compatibility;
12. update construction/factories/bindings only where required;
13. delete superseded AuditTrail pagination artifacts;
14. delete their obsolete tests;
15. update documentation;
16. run full validation.

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
* original throwable preservation.

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
| Package/Domain Isolation | `PACKAGE_BUILDING_STANDARD.md` | Keep domains separate | New namespace isolated to `Maatify\EventLogging\AuditTrail` | Contracts are purely AuditTrail owned | No Conflict |
| Public Admin Query Interface | `ADMIN_QUERY_API_ARCHITECTURE.md` | Framework agnostic read models | `AuditTrailAdminQueryInterface` has no HTTP or Framework bindings | Uses standard PHP | No Conflict |
| Request DTO | `PACKAGE_BUILDING_STANDARD.md` | Strict type validation | `AuditTrailAdminQueryRequestDTO` checks at construction | Uses named constructors | No Conflict |
| Result DTO | `PACKAGE_BUILDING_STANDARD.md` | Implement JSON & Iterators | `AuditTrailAdminPageResultDTO` does both | Adheres to list structure | No Conflict |
| Offset Pagination | `ADMIN_QUERY_API_ROADMAP.md` | Offset instead of cursor | Delegates pagination logic to persistence package | Uses PDO Pagination | No Conflict |
| Persistence Delegation | `PACKAGE_BUILDING_STANDARD.md` | Externalize generic concerns | Using `maatify/persistence` for the generic page logic | Persistence handles generic offsets | No Conflict |
| Filter Ownership | `ADMIN_QUERY_API_ARCHITECTURE.md` | Event Logging owns filters | Filters built using dedicated Builder class | Query Descriptor logic | No Conflict |
| SQL Ownership | `ADMIN_QUERY_API_ARCHITECTURE.md` | SQL built in package | Defined inside Repository/Builder | Explicit Query definitions | No Conflict |
| Semantic Count/Data Alignment | `ADMIN_QUERY_API_ARCHITECTURE.md`| Conditions match | Same builder method for count/data filters | Consistent Query Builder | No Conflict |
| Explicit Selected Columns | `PACKAGE_BUILDING_STANDARD.md` | No `SELECT *` | Explicitly lists all 19 columns | Defined in Builder | No Conflict |
| Deterministic Sorting | `PACKAGE_BUILDING_STANDARD.md` | Restrict sort options | Whitelists only `occurred_at` | Enforced by PDO Paginator | No Conflict |
| Tie-breaker | `PACKAGE_BUILDING_STANDARD.md` | Guarantee sorting order | Tie break using `id` | Enforced by PDO Paginator | No Conflict |
| Mapper Extraction | `PACKAGE_BUILDING_STANDARD.md` | No duplicate logic | `AuditTrailRowMapper` shared by both repos | Row Mapper internal | No Conflict |
| Exception Hierarchy | `PACKAGE_BUILDING_STANDARD.md` | Extends `SystemMaatifyException`| Exception implements rules | Extends exception base | No Conflict |
| Named Constructors | `PACKAGE_BUILDING_STANDARD.md` | Meaningful instantiation | Added strictly to Exceptions | Dedicated Methods | No Conflict |
| Dependency Direction | `PACKAGE_BUILDING_STANDARD.md` | Outward dependencies only | Relies exclusively on core maatify deps | Architecture rules | No Conflict |
| Composer Impact | `PACKAGE_BUILDING_STANDARD.md` | `maatify/persistence` | Defined to add dependency safely | Composer require update | No Conflict |
| No `composer.lock` | `PACKAGE_BUILDING_STANDARD.md` | Lock must not be tracked | Lock omitted | Not committed | No Conflict |
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
**Conflict:** The `PACKAGE_BUILDING_STANDARD.md` rule `No generic cross-domain DTOs or query abstractions` (and Section 15 Read/Admin Query API Rules) directly forbids returning the exact persistence boundary abstractions (`PageResult`, `PageRequest`) to the caller. However, `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md` (and `docs/audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md`) implied that `maatify/persistence` is the sole owner of generic pagination mechanics.
**Impact:** To satisfy both, the domain MUST wrap the persistence input/output types in its own specific `AuditTrailAdminQueryRequestDTO` and `AuditTrailAdminPageResultDTO`.
**Blueprint Decision:** Blocked at this decision until Owner approves. The blueprint proposes wrapping the persistence layer precisely within the domain adapter to avoid leaking generic page abstractions while relying on `maatify/persistence` internally.

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

## 15. Composer and Release Impact
* future addition: `maatify/persistence ^1.1.0`
* exact placement in require;
* no `composer.lock`;
* no schema migration expected;
* new additive public Admin Query API;
* removal of unreleased superseded post-v1 artifacts;
* compatibility with protected `v1.0.0` primitive contracts;
* expected Semantic Versioning impact: minor version update;
* expected future event-logging version: (TBD by release manager);
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