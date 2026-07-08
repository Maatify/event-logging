# Primitive Read/Query Support Design

**Status:** Approved
**Phase:** 3

## 1. Overview
This design document outlines the required primitive read/query support for the `maatify/event-logging` library, allowing host applications to build admin viewing functionality. It defines strict boundaries: the package provides pure query contracts and DTOs, while the host application owns controllers, UI, permissions, and complex analytics.

## 2. Public Read/Query Contracts
Each domain MUST implement its own isolated query interface. There MUST NOT be any shared or generic readers.

### Expected Contracts
*   `AuthoritativeAuditQueryInterface`
*   `AuditTrailQueryInterface`
*   `SecuritySignalsQueryInterface`
*   `BehaviorTraceQueryInterface`
*   `DiagnosticsTelemetryQueryInterface`
*   `DeliveryOperationsQueryInterface`

Each interface MUST expose a primary search/find method accepting a domain-specific `QueryDTO` and returning an array or iterable of `ViewDTO`s.

## 3. DTO Definitions

### Query DTOs
Each domain MUST have a dedicated `QueryDTO` (e.g., `AuditTrailQueryDTO`). These DTOs hold the allowed filter criteria.

### View DTOs
Each domain MUST have a dedicated `ViewDTO` (or `EventDTO` if completely overlapping) representing the structured output record. Metadata must be safely decoded to an array, defaulting to `null` on corruption, per the fail-open metadata policy.

### Cursor Pagination Shape
Pagination MUST be implemented using cursor-based pagination to support large datasets safely. The required shape for ALL domain `QueryDTO`s is:
*   `cursorOccurredAt` (`?DateTimeImmutable`)
*   `cursorId` (`?int`)
*   `limit` (`int`, default: `50`)

Ordering MUST be stable across all domains: `ORDER BY occurred_at DESC, id DESC`.

## 4. Allowed Filters Per Domain

| Filter / Dimension | AuthoritativeAudit | AuditTrail | SecuritySignals | BehaviorTrace | DiagnosticsTelemetry | DeliveryOperations |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| Date Range (`after`, `before`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Actor (`actorType`, `actorId`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Request ID | ❌ (not in schema) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Correlation ID | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Event/Action/Signal Type | ✅ (`action`) | ✅ (`eventKey`) | ✅ (`signalType`) | ✅ (`action`) | ✅ (`eventKey`) | ✅ (`operationType`) |
| Target/Entity | ✅ (`targetType`, `targetId`) | ✅ (`entityType`, `entityId`, `subjectType`, `subjectId`) | ❌ | ✅ (`entityType`, `entityId`) | ❌ | ✅ (`targetType`, `targetId`) |
| Severity | ❌ | ❌ | ✅ (`severity`) | ❌ | ✅ (`severity`) | ❌ |
| Channel | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (`channel`) |
| Status | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (`status`) |

*(Note: `AuthoritativeAudit` schema does not store `request_id`, so it is excluded).*

## 5. Library vs. Host Responsibilities

**Library (Event Logging Package) owns:**
*   Domain-specific `QueryInterface` and `QueryMysqlRepository`.
*   Domain-specific `QueryDTO` and `ViewDTO`.
*   Cursor pagination SQL logic.
*   Safe JSON metadata decoding behavior.

**Host Application owns:**
*   Controllers, routing, and middleware.
*   Admin UI and presentation layers.
*   Permissions, actor resolution, and authorization logic.
*   Exports, dashboards, and complex analytics.
*   Localization and labeling.

## 6. Architecture Constraints
To strictly maintain domain isolation and align with `PACKAGE_BUILDING_STANDARD.md`:
*   **NO** GenericReader or GenericQueryRepository.
*   **NO** GenericLogViewer or UI controllers.
*   **NO** GenericLogDTO.
*   **NO** Shared cross-domain query endpoints.
*   **NO** Framework-specific bindings (e.g., Slim, Laravel).

## 7. Exception Policy
*   **Domain-Specific:** All read and query methods MUST throw domain-specific exceptions (e.g., `AuditTrailStorageException`).
*   **Inheritance:** Domain exceptions MUST inherit from `Maatify\Exceptions\Exception\System\SystemMaatifyException` and use named constructors where appropriate.
*   **No Swallowing in Repositories:** Repositories and infrastructure layers MUST NOT swallow `Throwable` or `PDOException`. Exceptions must be caught and wrapped in the domain's storage exception.
*   **Fail-Open Write Path:** The fail-open swallowing (for non-authoritative domains) applies strictly at the `Recorder` boundary, not the read path.
*   **Metadata:** Read mapping MUST swallow `JsonException` and return `null` for corrupted metadata fields, ensuring the rest of the record is still readable.
