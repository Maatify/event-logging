# DiagnosticsTelemetry Admin Query Blueprint

**Status:** Proposed / Owner Review Required / Runtime Not Authorized

This document defines the complete proposed architecture for adding the new Admin Query API path for `DiagnosticsTelemetry`.

## 1. Classification and boundaries

*   This is a **New Admin Query API**, not a rebuild.
*   There is no obsolete `DiagnosticsTelemetry` wrapper deletion (it never received the incorrect post-v1 pagination wrapper).
*   No schema change.
*   No Composer or CI change.
*   No host controllers, routes, permissions, UI, exports, dashboards, or framework wiring.
*   Primitive `find()` and `read()` contracts remain supported and separate.

## 2. Proposed Public API

The exact proposed public interface and classes to be created in the later Runtime phase:

### Interfaces and Classes

```text
DiagnosticsTelemetryAdminQueryInterface
DiagnosticsTelemetryAdminQueryRequestDTO
DiagnosticsTelemetryAdminPageResultDTO
DiagnosticsTelemetryAdminQueryInvalidArgumentException
DiagnosticsTelemetryAdminQueryExecutionException
DiagnosticsTelemetryAdminQueryMysqlRepository
```

### Public Interface

The public interface must expose:

```php
public function paginate(
    DiagnosticsTelemetryAdminQueryRequestDTO $request
): DiagnosticsTelemetryAdminPageResultDTO;
```

The page result `DiagnosticsTelemetryAdminPageResultDTO` should contain existing `DiagnosticsTelemetryEventDTO` items and the canonical pagination metadata.

## 3. Filters

The `DiagnosticsTelemetryAdminQueryRequestDTO` will authorize only the following filters:

```text
actorType
actorId
eventKey
severity
requestId
correlationId
after
before
```

### Filter Rules

*   `actorType` and `actorId` are independent; type-only, ID-only, both, and neither are valid.
*   Positive IDs only.
*   Empty strings normalize to `null`.
*   String limits must match the current schema.
*   Equal date boundaries are valid.
*   Date filters are inclusive.
*   Do not add `eventId`, `routeName`, `durationMs`, metadata search, free-text search, arbitrary SQL, or generic filtering in this phase.

## 4. Sorting and Pagination

*   **Public sort key:** `occurred_at` only.
*   **Sort directions:** `ASC` and `DESC`.
*   **Deterministic internal tie-breaker:** `id`.
*   **Default:** `occurred_at DESC, id DESC`.
*   Delegate page/per-page normalization, clamping, offset, count execution, ordering mechanics, and metadata to `maatify/persistence`.
*   Use explicit selected columns, not `SELECT *`, in the new Admin path.

## 5. SQL and Count Semantics

*   **Table:** `maa_event_logging_diagnostics_telemetry`.
*   **`total`:** all table rows without optional Admin filters.
*   **`filtered`:** the same mandatory scope plus all accepted filters.
*   Data SQL must use exactly the same filters and parameters as `filtered`.
*   One internal descriptor/filter source must own filtered-count and data semantics.
*   No JOINs or host-table access.

## 6. Mapping

Define internal final classes:

```text
DiagnosticsTelemetryRowMapper
Pagination\DiagnosticsTelemetryAdminQueryDescriptorBuilder
```

The mapper must be reused by the primitive repository and the new Admin repository while preserving:

*   DiagnosticsTelemetry policy normalization.
*   Actor-type and severity fallback behavior.
*   UTC and six-digit microsecond hydration.
*   Nullable context fields.
*   `durationMs`.
*   valid, null, empty, and corrupt metadata behavior.
*   existing storage/mapping exception semantics.

## 7. Protected Primitive Correction

The existing primitive `find()` query reuses `:cursor_at` twice.
The later Runtime implementation must correct it to distinct native-PDO placeholders, for example:

```sql
(
    occurred_at < :cursor_at_before
    OR (
        occurred_at = :cursor_at_equal
        AND id < :cursor_id
    )
)
```

Bind the same exact six-digit timestamp to both timestamp placeholders.
Preserve all other primitive `find()` behavior.
Preserve the separate legacy `read()` behavior, including its ascending order and existing cursor direction.

## 8. Exceptions and Transactions

*   Invalid request values: `DiagnosticsTelemetryAdminQueryInvalidArgumentException`.
*   Descriptor/configuration failures: `DiagnosticsTelemetryAdminQueryExecutionException`.
*   PDO and paginator execution failures: existing `DiagnosticsTelemetryStorageException`.
*   Preserve the original throwable as `previous`.
*   Read repositories must not start, commit, or roll back caller-owned transactions.

## 9. Proposed Runtime File Inventory

The later Runtime, Unit, Regression, Integration, and documentation file expected to change or be created:

### Public Contracts
*   `src/DiagnosticsTelemetry/Contract/DiagnosticsTelemetryAdminQueryInterface.php`
*   `src/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminQueryRequestDTO.php`
*   `src/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminPageResultDTO.php`
*   `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryAdminQueryInvalidArgumentException.php`
*   `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryAdminQueryExecutionException.php`

### Infrastructure
*   `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryAdminQueryMysqlRepository.php`
*   `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryRowMapper.php` (Internal)
*   `src/DiagnosticsTelemetry/Infrastructure/Mysql/Pagination/DiagnosticsTelemetryAdminQueryDescriptorBuilder.php` (Internal)
*   `src/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryQueryMysqlRepository.php` (Update primitive cursor)

### Tests
*   `tests/Unit/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminQueryRequestDTOTest.php`
*   `tests/Unit/DiagnosticsTelemetry/DTO/DiagnosticsTelemetryAdminPageResultDTOTest.php`
*   `tests/Unit/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryRowMapperTest.php`
*   `tests/Unit/DiagnosticsTelemetry/Infrastructure/Mysql/Pagination/DiagnosticsTelemetryAdminQueryDescriptorBuilderTest.php`
*   `tests/Regression/DiagnosticsTelemetry/Infrastructure/Mysql/DiagnosticsTelemetryQueryMysqlRepositoryRegressionTest.php` (Coverage for find and read)
*   `tests/Integration/DiagnosticsTelemetry/DiagnosticsTelemetryAdminQueryMysqlRepositoryTest.php` (Strict native-MySQL Admin integration coverage)
*   `tests/Integration/DiagnosticsTelemetry/DiagnosticsTelemetryRepositoryTest.php` (Update strict native-MySQL primitive compatibility coverage)

### Documentation
*   `EVENT_LOGGING_PACKAGE_REFERENCE.md` (Update)
*   `docs/integration/ADMIN_READ_USAGE.md` (Update integration guide)
*   `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md` (Update)
*   `docs/audits/DOCUMENTATION_INVENTORY.md` (Update)
*   `src/DiagnosticsTelemetry/README.md` (Update domain README)
*   `CHANGELOG.md` (Update)

## 10. Runtime Verification Gate

The later Runtime gate must prove:
*   every filter independently and combined;
*   total/filtered/data alignment;
*   page/per-page min/max and page navigation;
*   ASC/DESC/default/fallback sorting;
*   deterministic equal-timestamp tie-breaking;
*   inclusive UTC microsecond boundaries;
*   nullable fields and metadata hydration;
*   policy fallback behavior;
*   primitive cursor correction;
*   legacy `read()` preservation;
*   storage exception previous throwable;
*   caller-owned transaction preservation;
*   no schema, Composer, CI, host, reporting, or dashboard changes.
