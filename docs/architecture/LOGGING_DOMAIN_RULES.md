# Logging Domain Rules

**Status:** CANONICAL
**Scope:** Defines the **six canonical logging domains**, their intent, boundaries, and classification rules for `maatify/event-logging`.

---

## 1. The Six Canonical Domains

The unified logging system enforces exactly six distinct logging domains. No additional domains may be created, and generic cross-domain logging is explicitly forbidden.

### 1. AuthoritativeAudit
- **Intent:** Governance, security posture, compliance, and strict state transitions.
- **Failure Mode:** **Fail-closed**. Storage failures must propagate as exceptions (e.g., `SystemMaatifyException`).
- **Data Lifecycle:** Immutable. Logged data represents the undeniable source of truth.

### 2. AuditTrail
- **Intent:** Reads, views, exports, navigation, and data exposure tracking.
- **Failure Mode:** **Fail-open**. Logging errors are caught at the recorder boundary (optional fallback to PSR-3).
- **Data Lifecycle:** Temporal record of who viewed what and when.

### 3. SecuritySignals
- **Intent:** Authentication events, security anomalies, blocking actions, and brute-force indicators.
- **Failure Mode:** **Fail-open**.
- **Data Lifecycle:** Temporary retention; primarily used for immediate signaling and active operational threat detection.

### 4. BehaviorTrace (Operational Activity)
- **Intent:** Operational mutations, user flows, and day-to-day business actions.
- **Note:** `BehaviorTrace` is the package implementation name for the conceptual "Operational Activity" domain.
- **Failure Mode:** **Fail-open**.
- **Data Lifecycle:** Standard retention; used for support, debugging, and historical user activity tracing.

### 5. DiagnosticsTelemetry
- **Intent:** Technical observability, latency, external API call traces, exception tracking, and system performance.
- **Failure Mode:** **Fail-open**.
- **Data Lifecycle:** Short retention; operational observability only.

### 6. DeliveryOperations
- **Intent:** Asynchronous jobs, queues, notifications, webhooks, and delivery lifecycle events.
- **Failure Mode:** **Fail-open**.
- **Data Lifecycle:** Standard retention; used for delivery guarantees, retry analysis, and queue inspection.

## 2. Shared Behavior (Fail-Open)
Except for `AuthoritativeAudit`, all domains are strictly **fail-open**. The `Recorder::record()` methods must catch all `Throwable` exceptions at the boundary, ensuring host applications are not disrupted by logging infrastructure failures. An optional PSR-3 logger may be provided to the package for fail-open fallback logging.
