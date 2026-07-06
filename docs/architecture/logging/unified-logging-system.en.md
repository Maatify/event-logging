# 📘 English Version

## **Unified Logging System — Canonical Architecture Document (Source of Truth)**

**Status:** Approved / Canonical
**Purpose:** Single authoritative reference for design, implementation, and review

---

## 1. System Purpose

Build a strict logging architecture that prevents semantic mixing and enables:

* Security audits
* Incident investigations
* Behavior analysis
* Performance optimization

### Core Outcomes

* Each event logged in exactly **one domain**
* One MySQL table per domain, column-searchable
* No secrets or sensitive data logged
* Design extractable into independent libraries

---

## 2. Golden Rule: One-Domain Rule

Every logged event belongs to **one domain only**, based on its **primary intent**.

If an action has multiple intents:

* ❌ Do not duplicate the same event
* ✅ Log **separate events** per intent with minimal metadata

---

## 3. Canonical Domains (Final)

1. Authoritative Audit
2. Audit Trail
3. Security Signals
4. Operational Activity
5. Diagnostics Telemetry
6. Delivery Operations

No additional domains are allowed.

---

## 4. Domain Definitions

### 4.1 Authoritative Audit

* Governance-grade, fail-closed
* **Source of truth:** `authoritative_audit_outbox` (transactional)
* Log tables are materialized views only

---

### 4.2 Audit Trail

* Data exposure & navigation
* Answers “who saw what, when”

---

### 4.3 Security Signals

* Best-effort security indicators
* No control-flow impact
* Non-transactional

---

### 4.4 Operational Activity

* Mutations only (create/update/delete)
* No reads or exports

---

### 4.5 Diagnostics Telemetry

* Technical observability
* Sanitized, no PII, best-effort

---

### 4.6 Delivery Operations

* Async lifecycle (emails, jobs, webhooks)

---

## 5. Unified Pipeline

```
HTTP/UI
 → Recorder
   → Writer/Logger
     → MySQL
```

### Responsibilities

* **Recorder**

    * Build DTO
    * Aggregate context
    * Apply policy
* **Writer**

    * Persist DTO only
    * No policy or DTO construction

Controllers/Services must not log directly.

---

## 5.1 Failure Semantics (Canonical)

### Non-Authoritative Domains (Best-Effort, Fail-Open)

For the following domains:
- Audit Trail
- Security Signals
- Operational Activity
- Diagnostics Telemetry
- Delivery Operations

**Recorder Contract (Hard Rule):**
- `Recorder::record()` MUST be fail-open and MUST NOT throw under any condition.
- Therefore, the Recorder MUST catch `Throwable` at the top-level boundary of `record()`.
- After catching `Throwable`, the Recorder MUST swallow (never rethrow) and MUST surface the failure via a safe operational channel (PSR-3 and/or a last-resort primitive channel).

**Infrastructure Contract (Hard Rule):**
- Storage drivers / repositories MUST remain honest: they MUST NOT swallow.
- They MUST throw domain-specific storage exceptions.

**Recursion Guard (Hard Rule):**
- Failure reporting MUST NOT call any logging recorder/writer again.
- The last-resort channel MUST be primitive (e.g., `error_log`, syslog, stderr) and MUST NOT depend on DTOs/UUID/JSON encoding.

### Authoritative Audit (Fail-Closed)

- Authoritative Audit is integrity critical.
- Outbox write failures are NOT best-effort and MUST be handled as system integrity failures.

---

## 6. Normalized Context

* event_id (UUID)
* actor_type / actor_id
* correlation_id
* request_id
* route_name
* ip_address
* user_agent
* occurred_at (DATETIME(6), **UTC only**)

---

## 7. request_id vs correlation_id

* **request_id:** one HTTP request
* **correlation_id:** spans multiple requests in one business workflow

---

## 8. Security Hard Rules

Never log:

* passwords
* OTPs
* access tokens
* secrets or keys

URLs:

* path only
* no query strings

`referrer_path` must be sanitized and masked.

---

## 9. Metadata Policy

* Structured JSON only
* Minimal fields
* **Max size: 64KB**
* Enforced at application layer

**Read-Mapping Corruption Tolerance (Explicit Exception):**
- Reader implementations MAY swallow JSON decode errors for `metadata` ONLY during read-mapping.
- In case of corruption, `metadata` MUST become `null` and the event MUST still be returned.
- No other swallowing is permitted in readers.

---

## 10. actor_type Allowed Values

* SYSTEM
* ADMIN
* USER
* SERVICE
* API_CLIENT
* ANONYMOUS

Validated at application layer.

---

## 11. Storage Baseline

* MySQL 5.7+
* Separate tables per domain
* Deterministic paging: `(occurred_at, id)`

**PDO Numeric Hydration Rule (MySQL):**
- Numeric columns (e.g., BIGINT) MAY be returned as strings by PDO.
- Query mappers MUST accept numeric strings and cast safely (e.g., `is_numeric` then `(int)`), instead of relying on `is_int` only.

---

## 12. Archiving (Mode B — Optional)

* MySQL → MySQL
* `*_archive` tables
* Same schema and indexes
* Separate SQL file
* Move-then-delete only

---

## 13. Operational Policies (Defaults)

### Outbox Processing

* Exponential retries
* Max attempts (default 10)
* Dead letter on exhaustion
* Lag alerts

### Delivery Retries

* Max attempts: 5
* Exponential backoff
* Terminal failure state

### Archiving

* Default: records > 90 days
* Batch: 10K
* Verified move before delete

---

## 14. Examples

* `login_failed` → Security Signals
* `create_admin` →

    * Authoritative Audit
    * Operational Activity

---

## 15. Document Status

✅ **Approved — Source of Truth**
Any future change requires a formal architectural review.

---
