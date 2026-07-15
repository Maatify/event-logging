# AuthoritativeAudit Admin Query Rebuild Blueprint

**Status:** Proposed / Pending Owner Approval

This document defines the complete proposed architecture for replacing the superseded post-v1 AuthoritativeAudit pagination wrapper with a package-owned Admin Query API.

It establishes the blueprint for the final remediation phase, ensuring strict fail-closed behavior, protected transaction boundaries, and separation from outbox semantics. It authorizes a separate Runtime implementation task/PR once approved, but it does **not** itself implement Runtime, tagging, or release work.

---

## 1. Audited Baseline

- **Audit Date:** 2026-07-15
- **Purpose:** Rebuild post-v1 pagination experiment into Admin Query API architecture.

### 1.1 Runtime and schema sources reviewed

- `src/AuthoritativeAudit/Contract/AuthoritativeAuditQueryInterface.php`
- `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditViewDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTO.php`
- `src/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditQueryMysqlRepository.php`
- `src/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryService.php`

### 1.2 Governing documents reviewed

- `AGENTS.md`
- `EVENT_LOGGING_PACKAGE_REFERENCE.md`
- `docs/standards/PACKAGE_BUILDING_STANDARD.md`
- `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md`
- `docs/audits/ADMIN_QUERY_AUTHORITATIVE_AUDIT_AUDIT.md`
- `docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md`
- `docs/architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md`
- `docs/architecture/ADMIN_QUERY_SECURITY_SIGNALS_REBUILD_BLUEPRINT.md`

### 1.3 Required separation

The audit distinguishes three different contracts:

1. **Protected `v1.0.0` Runtime**
   - primitive public interface;
   - primitive query and view DTOs;
   - primitive repository constructor and observable behavior;
   - schema (fail-closed outbox and materialized log);
   - row hydration;
   - storage exception boundary;
   - fail-closed reliability boundary.
2. **Superseded post-v1 pagination experiment**
   - four Runtime wrapper artifacts (PaginatedQueryInterface, Service, CursorDTO, PageDTO);
   - directly associated unit tests;
   - replaced and deleted atomically inside the approved Runtime rebuild.
3. **Approved Admin Query path**
   - AuthoritativeAudit-specific package API;
   - offset/page pagination through `maatify/persistence`;
   - strict read-from-log restriction;
   - no change to the primitive public contract.

---

## 2. Protected Primitive Contract

### 2.1 Public interface

The protected method is exactly:

```php
public function find(AuthoritativeAuditQueryDTO $query): array;
```

### 2.2 DTO and validation

The `AuthoritativeAuditQueryDTO` constructor signature, default values, and serialization keys are protected.
- Cursor is activated only if both `cursorOccurredAt` and `cursorId` are strictly not null.
- Limit is normalized as `max(1, $limit)`.

### 2.3 Storage and boundary semantics

- Reading strictly targets the log table (`maa_event_logging_authoritative_audit_log`), never the outbox.
- Write/outbox semantics (fail-closed) remain unchanged.
- Read repositories must not start, commit, or rollback transactions. They must maintain strict caller-owned transaction semantics.

### 2.4 Hydration behavior

Current hydration logic mapped from database results to `AuthoritativeAuditViewDTO` is strictly preserved, including fallbacks:
- `id`: int (fallback to 0)
- `event_id`: string (fallback to '')
- `actor_type`: string/null
- `actor_id`: int/null
- `action`: string (fallback to '')
- `target_type`: string/null
- `target_id`: int/null
- `ip_address`: string/null
- `user_agent`: string/null
- `correlation_id`: string/null
- `changes`: valid JSON array -> array, otherwise null
- `occurred_at`: date (fallback to '1970-01-01 00:00:00')

### 2.5 Exceptions

The current exceptions:
- Query failure: `Failed to query AuthoritativeAudit records: {message}`
- Hydration failure: `Failed to map AuthoritativeAudit row: {message}`
Both wrapped as `AuthoritativeAuditStorageException`.

---

## 3. Target Admin Query API


### 3.1 Public Interface and Implementation

```php
namespace Maatify\EventLogging\AuthoritativeAudit\Contract;


use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminQueryRequestDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditAdminPageResultDTO;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryExecutionException;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;

interface AuthoritativeAuditAdminQueryInterface
{
    /**
     * @throws AuthoritativeAuditStorageException
     * @throws AuthoritativeAuditAdminQueryExecutionException
     * @throws AuthoritativeAuditAdminQueryInvalidArgumentException
     */
    public function paginate(AuthoritativeAuditAdminQueryRequestDTO $request): AuthoritativeAuditAdminPageResultDTO;

}
```

The concrete implementation will be `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditAdminQueryMysqlRepository`.

### 3.2 Request DTO

```php
namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use JsonSerializable;

final readonly class AuthoritativeAuditAdminQueryRequestDTO implements JsonSerializable
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public string $sort = 'occurred_at',
        public string $direction = 'DESC',
        public ?string $eventId = null,
        public ?string $actorType = null,
        public ?int $actorId = null,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?string $action = null,
        public ?string $correlationId = null,
        public ?DateTimeImmutable $after = null,
        public ?DateTimeImmutable $before = null
    ) {
    }

    // jsonSerialize method implementing strict types
}
```

### 3.3 Page Result DTO

```php
namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use IteratorAggregate;
use JsonSerializable;
use ArrayIterator;
use Traversable;

/**
 * @implements IteratorAggregate<int, AuthoritativeAuditViewDTO>
 */
final readonly class AuthoritativeAuditAdminPageResultDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<AuthoritativeAuditViewDTO> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $filtered,
        public int $page,
        public int $perPage
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    // jsonSerialize method
}
```

### 3.4 Filter Independence

- Every filter is independent.
- `actorType` and `actorId` can be queried independently.
- `targetType` and `targetId` can be queried independently.
- When both parts of a type/id pair are provided, they use `AND`.
- Positive IDs only (no negative IDs, no NULL search).

### 3.5 Global Search Exclusion

Global search is explicitly excluded from this domain implementation and recorded as a future unified cross-domain extension.

### 3.6 Sorting and Pagination

- Sorting is caller-selectable only on `occurred_at`.
- Directions: `ASC` and `DESC`.
- Internal tie-breaker strictly on `id DESC` (or `id ASC` depending on direction).
- Pagination normalization (page limits, clamping to max 200) is fully owned by `maatify/persistence`. Default is 20, min 1, max 200.

### 3.7 SQL and Execution

- SQL explicitly lists columns instead of `SELECT *`.
- Target table: `maa_event_logging_authoritative_audit_log` only.
- Strict caller-owned transactions; read operations do not open, commit, or rollback transactions.
- Filtered count and data query use the same WHERE clause generator and parameters.
- Shared RowMapper `/** @internal */ final class AuthoritativeAuditRowMapper`

---

## 4. Exceptions

- `AuthoritativeAuditAdminQueryInvalidArgumentException`: Used for invalid filter/sort inputs.
- `AuthoritativeAuditAdminQueryExecutionException`: Used for pagination execution setup failures.
- `AuthoritativeAuditStorageException`: Used for PDO execution and row mapping failures.
- The previous throwable must be preserved in all translations.
- If the RowMapper throws `AuthoritativeAuditStorageException`, the repository passes it through without re-wrapping.

---

## 5. Primitive Cursor Correction

The primitive `find` method uses the `:cursor_at` placeholder twice. It must be split to support native prepared statements:

```sql
(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))
```
Both placeholders will receive the exact same value.

---

## 6. Atomic Retirement

Exactly 7 files are superseded post-v1 artifacts and must be deleted during implementation:
- `src/AuthoritativeAudit/Contract/AuthoritativeAuditPaginatedQueryInterface.php`
- `src/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryService.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTO.php`
- `src/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTO.php`
- `tests/Unit/AuthoritativeAudit/Service/AuthoritativeAuditPaginatedQueryServiceTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryPageDTOTest.php`
- `tests/Unit/AuthoritativeAudit/DTO/AuthoritativeAuditQueryCursorDTOTest.php`

---

## 7. Testing Contract

- **Unit:** Complete coverage for DTOs, mappers, descriptor builder, and exceptions.
- **Regression:** Prove primitive find() cursor descending behavior, exact storage exception messages, schema stability, and existing hydration.
- **Integration:** Strict real-MySQL tests using native PDO placeholder rules. Proves all filters, independent pair querying, count vs data semantic alignment, and valid nullable mapping, without relying on SQLite. Includes test for corrupt JSON handling where possible.
- **Transactions:** Prove read repositories do not manage transactions.
- **Host Search:** Evidence that host usage searches yield no dependencies on the deleted wrapper artifacts.
