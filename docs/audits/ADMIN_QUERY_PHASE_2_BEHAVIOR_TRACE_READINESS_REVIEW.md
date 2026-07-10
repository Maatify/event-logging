# Phase 2 Readiness Review: BehaviorTrace Admin Query API

> Superseded: this readiness review discussed the now-rejected cursor-based paginated query service pattern. It is retained as audit history only; Section 11 of `docs/standards/PACKAGE_BUILDING_STANDARD.md` remains the source of truth.


## 1. Verdict
**NEEDS IMPLEMENTATION FIX**

## 2. Reviewed Files
- `src/BehaviorTrace/Contract/BehaviorTraceQueryInterface.php`
- `src/BehaviorTrace/DTO/BehaviorTraceQueryDTO.php`
- `src/BehaviorTrace/DTO/BehaviorTraceEventDTO.php`
- `src/BehaviorTrace/DTO/BehaviorTraceContextDTO.php`
- `src/BehaviorTrace/DTO/BehaviorTraceCursorDTO.php`
- `src/BehaviorTrace/Infrastructure/Mysql/BehaviorTraceQueryMysqlRepository.php`

## 3. Current Shape Summary
- `BehaviorTraceQueryInterface::find()` returns an array of `BehaviorTraceEventDTO`.
- `BehaviorTraceEventDTO` does not expose an `id` property.
- `BehaviorTraceEventDTO` does not have a direct `occurredAt` property; the timestamp is nested within `BehaviorTraceContextDTO` (`$this->context->occurredAt`).
- `BehaviorTraceQueryDTO` accepts `cursorOccurredAt` and `cursorId`.
- `BehaviorTraceQueryMysqlRepository` correctly filters and orders by `occurred_at DESC, id DESC` in SQL.
- Unlike `AuditTrail`, `AuthoritativeAudit`, and `SecuritySignals`, `BehaviorTrace` does not have a dedicated `ViewDTO` for query results.

## 4. Feasibility Analysis
- **Can we apply the same pattern directly?** No, not until `id` is added to `BehaviorTraceEventDTO`.
- **Can we build a valid nextCursor?** No. The established paginated query service pattern relies on extracting the `id` and `occurredAt` from the last actual item in the retrieved page to construct the next cursor.
- **Is `id` available?** No. The `BehaviorTraceEventDTO` returned by the repository does not contain the database `id`, making it impossible to pass it back in the cursor.

## 5. Architectural Decision & Reasoning
The library is still prior to its first official release (pre-1.0.0). Therefore, modifying `BehaviorTraceEventDTO` to expose the database `id` is permitted and is not considered a breaking public API change.

`BehaviorTraceQueryDTO` already accepts:
- `cursorOccurredAt`
- `cursorId`

And `BehaviorTraceQueryMysqlRepository` effectively uses:
- `occurred_at`
- `id`

for ordering and filtering in cursor pagination.

Because of this, any DTO returned from the query layer without an `id` is considered incomplete for the Admin Query API. Leaving it out will create issues later in:
- cursor pagination
- admin read models
- debugging
- reconciliation
- stable row references

## 6. Required Implementation Fixes
Before the paginated query pattern can be applied, the following fixes must be implemented:
1. Modify `BehaviorTraceEventDTO` to contain the `id` property.
2. Update `BehaviorTraceQueryMysqlRepository::mapRowToDTO()` to pass the `id`.
3. Update any affected tests to reflect these changes.

## 7. Decision
`BehaviorTrace` is **NOT** deferred.
Once the required implementation fixes outlined above are completed (exposing the `id` in the DTO), Codex can successfully execute the domain-scoped paginated query pattern on `BehaviorTrace`.
