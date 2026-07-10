# Phase 2 Readiness Review: BehaviorTrace Admin Query API

## 1. Verdict
**DEFER**

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
- **Can we apply the same pattern directly?** No.
- **Can we build a valid nextCursor?** No. The established paginated query service pattern relies on extracting the `id` and `occurredAt` from the last actual item in the retrieved page to construct the next cursor.
- **Is `id` available?** No. The `BehaviorTraceEventDTO` returned by the repository does not contain the database `id`, making it impossible to pass it back in the cursor.

## 5. Risk Analysis
- **Public API Risk:** Adding `id` to `BehaviorTraceEventDTO` mixes persistence details into an event DTO, breaking conceptual purity. Creating a new `BehaviorTraceViewDTO` and changing the return type of `BehaviorTraceQueryInterface::find()` would be a breaking change for existing consumers.
- **Domain Boundary Risk:** Minimal, as any changes (like a new View DTO) would remain strictly within the `BehaviorTrace` namespace.
- **Read/Streaming API Confusion Risk:** Using the existing `read()` method for the Admin API is architecturally incorrect. The `read()` method is designed for forward-streaming (ordered `ASC`, useful for exports or processing) rather than backward-facing, user-facing admin pagination (ordered `DESC`).
- **Backward Compatibility Risk:** High if we modify the existing query interface. Safe if we introduce a dedicated admin query contract, but this requires a design decision.

## 6. Recommended Path
**Option C**
**Reason:** `BehaviorTrace` currently lacks the `ViewDTO` structure necessary to support cursor pagination without structural API changes. It is better to defer this domain and maintain the momentum of Phase 2 by addressing a domain that already aligns with the established pattern (i.e., one that returns a `ViewDTO` containing an `id`).

## 7. Decision
Do **NOT** send Codex to execute `BehaviorTrace` now. We will defer `BehaviorTrace` until a design decision is made regarding how to safely expose the `id` for pagination (e.g., introducing an Admin View DTO and a new contract). We will move to the next compatible domain.

## 8. Next Domain
`DeliveryOperations`