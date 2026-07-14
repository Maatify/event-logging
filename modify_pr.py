import re

with open("docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md", "r") as f:
    content = f.read()

# 1. Fix the Descriptor Builder Return Contract
old_desc_contract = """* `buildFilteredWhereAndParams()` must build the conditions and parameters once.
* It must return `[$conditions, $params]`.
* Dates must be converted to UTC before `Y-m-d H:i:s.u` formatting inside the descriptor builder:
  ```php
  $request->after?->setTimezone(new \\DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
  ```
* The exact filter-to-SQL mapping must be used:
  * `actorType` → `actor_type = :actor_type`
  * `actorId` → `actor_id = :actor_id`
  * `eventKey` → `event_key = :event_key`
  * `entityType` → `entity_type = :entity_type`
  * `entityId` → `entity_id = :entity_id`
  * `subjectType` → `subject_type = :subject_type`
  * `subjectId` → `subject_id = :subject_id`
  * `requestId` → `request_id = :request_id`
  * `correlationId` → `correlation_id = :correlation_id`
  * `after` → `occurred_at >= :after`
  * `before` → `occurred_at <= :before`
* `build()` must reuse the returned params for both filtered-count and data queries.
* `$whereSql` must be an empty string when no filters exist; otherwise `WHERE ` followed by conditions joined with ` AND `.
* `$totalSql` must be: `SELECT COUNT(id) FROM maa_event_logging_audit_trail`
* `$filteredCountSql` must be: `SELECT COUNT(id) FROM maa_event_logging_audit_trail ` . $whereSql
* `$dataSql` must be: `SELECT id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at FROM maa_event_logging_audit_trail ` . $whereSql

Returns exactly:
```php
return new PdoPaginationQueryDescriptor(
    totalSql: $totalSql,
    totalParams: [],
    filteredCountSql: $filteredCountSql,
    filteredCountParams: $params,
    dataSql: $dataSql,
    dataParams: $params,
);
```"""

new_desc_contract = """* `buildFilteredWhereAndParams()` must build the conditions and parameters once.
* It must use one exact return shape:
```php
/**
 * @return array{
 *     whereSql: string,
 *     params: array<string, string|int|bool|null>
 * }
 */
private function buildFilteredWhereAndParams(
    AuditTrailAdminQueryRequestDTO $request
): array;
```
* Dates must be converted to UTC before `Y-m-d H:i:s.u` formatting inside the descriptor builder:
  ```php
  $request->after?->setTimezone(new \\DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
  ```
* The exact filter-to-SQL mapping must be used:
  * `actorType` → `actor_type = :actor_type`
  * `actorId` → `actor_id = :actor_id`
  * `eventKey` → `event_key = :event_key`
  * `entityType` → `entity_type = :entity_type`
  * `entityId` → `entity_id = :entity_id`
  * `subjectType` → `subject_type = :subject_type`
  * `subjectId` → `subject_id = :subject_id`
  * `requestId` → `request_id = :request_id`
  * `correlationId` → `correlation_id = :correlation_id`
  * `after` → `occurred_at >= :after`
  * `before` → `occurred_at <= :before`
* Provide the complete method flow:
```php
$conditions = [];
$params = [];

// append exact conditions and params

$whereSql = $conditions === []
    ? ''
    : ' WHERE ' . implode(' AND ', $conditions);

return [
    'whereSql' => $whereSql,
    'params' => $params,
];
```

* Then define `build()` exactly:
```php
$filtered = $this->buildFilteredWhereAndParams($request);
$whereSql = $filtered['whereSql'];
$params = $filtered['params'];

$totalSql = 'SELECT COUNT(*) FROM maa_event_logging_audit_trail';
$filteredCountSql =
    'SELECT COUNT(*) FROM maa_event_logging_audit_trail'
    . $whereSql;
$dataSql =
    'SELECT id, event_id, actor_type, actor_id, event_key, entity_type, entity_id, '
    . 'subject_type, subject_id, referrer_route_name, referrer_path, referrer_host, '
    . 'correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at '
    . 'FROM maa_event_logging_audit_trail'
    . $whereSql;

return new PdoPaginationQueryDescriptor(
    totalSql: $totalSql,
    totalParams: [],
    filteredCountSql: $filteredCountSql,
    filteredCountParams: $params,
    dataSql: $dataSql,
    dataParams: $params,
);
```"""

content = content.replace(old_desc_contract, new_desc_contract)

# Also fix the previous signature definition from section 4.
old_sig = """```php
private function buildFilteredWhereAndParams(
    AuditTrailAdminQueryRequestDTO $request
): array;
```"""
# We just removed it if it is duplicated, or let it be replaced. Let's find it.
if old_sig in content:
    content = content.replace(old_sig, "")

# 2. Correct UTF-8 Length Validation
old_utf8_valid = """    private static function normalizeNullableString(
        ?string $value,
        string $field,
        int $maxLength
    ): ?string {
        if ($value === null) return null;
        $trimmed = trim($value);
        if ($trimmed === '') return null;
        if (strlen($trimmed) > $maxLength) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidLength($field);
        }
        return $trimmed;
    }"""
new_utf8_valid = """    private static function utf8Length(string $value, string $field): int
    {
        $length = preg_match_all('/./us', $value);

        if ($length === false) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        return $length;
    }

    private static function normalizeNullableString(
        ?string $value,
        string $field,
        int $maxLength
    ): ?string {
        if ($value === null) return null;
        $trimmed = trim($value);
        if ($trimmed === '') return null;
        if (self::utf8Length($trimmed, $field) > $maxLength) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidLength($field);
        }
        return $trimmed;
    }"""
content = content.replace(old_utf8_valid, new_utf8_valid)

old_utf8_desc = """Construct-time validation is performed immediately. No validators delegated. Page and per-page are passed raw without local numeric normalization. MySQL date string formatting occurs exclusively inside the descriptor builder, not the DTO json layer. Note that string length limits are validated as byte lengths using `strlen()` rather than `mb_strlen()` to match the current package Runtime dependencies (which do not require `ext-mbstring`) and MySQL column byte limits. `ext-mbstring` is neither required nor proposed."""
new_utf8_desc = """Construct-time validation is performed immediately. No validators delegated. Page and per-page are passed raw without local numeric normalization. MySQL date string formatting occurs exclusively inside the descriptor builder, not the DTO json layer. Note that string length limits match the character semantics of the current `utf8mb4` `VARCHAR`/`CHAR` schema without requiring `ext-mbstring`. The `invalidEncoding(string $field)` must be added to the exact named-constructor plan and its tests."""
content = content.replace(old_utf8_desc, new_utf8_desc)


# 3. Make Configuration Exception Translation Reachable
old_pagination_config_construct = """        $this->paginationConfig = new PaginationConfig(
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
        );"""

new_pagination_config_construct = """    }

    private function createPaginationConfig(): PaginationConfig
    {
        return new PaginationConfig(
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
        );"""
content = content.replace(old_pagination_config_construct, new_pagination_config_construct)
# Need to remove the old field:
content = content.replace("    private PaginationConfig $paginationConfig;\n", "")


old_paginate = """    public function paginate(AuditTrailAdminQueryRequestDTO $request): AuditTrailAdminPageResultDTO
    {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection
        );

        try {
            $descriptor = $this->descriptorBuilder->build($request);
            $result = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $this->paginationConfig,
                fn (array $row): AuditTrailViewDTO => $this->mapper->map($row)
            );"""

new_paginate = """    public function paginate(AuditTrailAdminQueryRequestDTO $request): AuditTrailAdminPageResultDTO
    {
        $pageRequest = new PageRequest(
            page: $request->page,
            perPage: $request->perPage,
            sortBy: $request->sortBy,
            sortDirection: $request->sortDirection
        );

        try {
            $descriptor = $this->descriptorBuilder->build($request);
            $paginationConfig = $this->createPaginationConfig();

            $result = $this->paginator->paginate(
                $this->pdo,
                $descriptor,
                $pageRequest,
                $paginationConfig,
                fn (array $row): AuditTrailViewDTO => $this->mapper->map($row),
            );"""
content = content.replace(old_paginate, new_paginate)

# 4. Complete the Marker Prerequisite Inventory
old_exc_rec = """This prerequisite must update exactly the following existing package-defined exceptions to directly or indirectly implement the marker:
* `src/AuditTrail/Exception/AuditTrailStorageException.php` (`AuditTrailStorageException`)
* `src/AuthoritativeAudit/Exception/AuthoritativeAuditStorageException.php` (`AuthoritativeAuditStorageException`)
* `src/BehaviorTrace/Exception/BehaviorTraceStorageException.php` (`BehaviorTraceStorageException`)
* `src/DeliveryOperations/Exception/DeliveryOperationsStorageException.php` (`DeliveryOperationsStorageException`)
* `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryStorageException.php` (`DiagnosticsTelemetryStorageException`)
* `src/SecuritySignals/Exception/SecuritySignalsStorageException.php` (`SecuritySignalsStorageException`)

For each of these exceptions, their constructor, message, error code (`ErrorCodeEnum::DATABASE_CONNECTION_FAILED`), and failure behavior must remain entirely unchanged.

Prerequisite tests needed to prove package-wide marker compliance must include tests that instantiate each of these exceptions and assert that they implement `Maatify\\EventLogging\\Exception\\EventLoggingExceptionInterface`, extend `SystemMaatifyException`, and return the expected `ErrorCodeEnum::DATABASE_CONNECTION_FAILED` default error code.

This POC implementation remains **blocked** until that prerequisite decision is approved and completed."""

new_exc_rec = """This prerequisite must update exactly the following existing package-defined exceptions to implement the marker **directly** (because no package-owned common exception base currently exists):
* `src/AuditTrail/Exception/AuditTrailStorageException.php` (`AuditTrailStorageException`)
* `src/AuthoritativeAudit/Exception/AuthoritativeAuditStorageException.php` (`AuthoritativeAuditStorageException`)
* `src/BehaviorTrace/Exception/BehaviorTraceStorageException.php` (`BehaviorTraceStorageException`)
* `src/DeliveryOperations/Exception/DeliveryOperationsStorageException.php` (`DeliveryOperationsStorageException`)
* `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryStorageException.php` (`DiagnosticsTelemetryStorageException`)
* `src/SecuritySignals/Exception/SecuritySignalsStorageException.php` (`SecuritySignalsStorageException`)

For each of these exceptions, their constructor, message, error code (`ErrorCodeEnum::DATABASE_CONNECTION_FAILED`), and failure behavior must remain entirely unchanged.

Prerequisite tests needed to prove package-wide marker compliance must include this exact test:
`tests/Unit/Exception/EventLoggingExceptionInterfaceTest.php`
Class:
`Maatify\\EventLogging\\Tests\\Unit\\Exception\\EventLoggingExceptionInterfaceTest`

The test must prove for all six exceptions:
* instance of `EventLoggingExceptionInterface`;
* instance of `SystemMaatifyException`;
* existing default error code remains `DATABASE_CONNECTION_FAILED`;
* existing construction and previous-throwable behavior remain unchanged.

Also add the root package-reference update required by the standard:
`EVENT_LOGGING_PACKAGE_REFERENCE.md`
as part of the separate prerequisite PR, not PR #96.

This POC implementation remains **blocked** until that prerequisite decision is approved and completed."""
content = content.replace(old_exc_rec, new_exc_rec)


# Update the Runtime file plan to include all 6 exceptions
lines = content.split('\n')
new_lines = []
for line in lines:
    if "`src/AuditTrail/Exception/AuditTrailStorageException.php` | MODIFY (Prerequisite)" in line:
        new_lines.append("| `src/AuditTrail/Exception/AuditTrailStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |")
        new_lines.append("| `src/AuthoritativeAudit/Exception/AuthoritativeAuditStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |")
        new_lines.append("| `src/BehaviorTrace/Exception/BehaviorTraceStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |")
        new_lines.append("| `src/DeliveryOperations/Exception/DeliveryOperationsStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |")
        new_lines.append("| `src/DiagnosticsTelemetry/Exception/DiagnosticsTelemetryStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |")
        new_lines.append("| `src/SecuritySignals/Exception/SecuritySignalsStorageException.php` | MODIFY (Separate Owner-Approved Prerequisite) | Implement marker | Additive marker |")
    else:
        new_lines.append(line)
content = '\n'.join(new_lines)


with open("docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md", "w") as f:
    f.write(content)
