# LOG_STORAGE_AND_ARCHIVING

> **Project:** maatify/admin-control-panel
> **Status:** CANONICAL (Binding — Subordinate to unified-logging-system.*)
> **Scope:** Defines **baseline storage** and **optional archiving** rules for the Unified Logging System.
> **Terminology Source of Truth:** `docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md`
> **Architecture Source of Truth:**
>
> * `unified-logging-system.ar.md`
> * `unified-logging-system.en.md`
    >   If a conflict exists, the Unified Logging System documents win.

---

## 0) Baseline First (Hard Rule)

The logging system MUST be **fully correct and complete using MySQL only**.

* MongoDB or any non-MySQL backend is **NOT assumed**.
* Archiving is **OPTIONAL** and **NOT required** for baseline correctness.
* Any archiving implementation MUST:

    * Be explicitly enabled
    * Be documented
    * Preserve searchability by columns
* Any archiving model other than Mode B (MySQL → MySQL) is non-canonical
    and explicitly unsupported.

This rule exists to ensure portability across constrained or shared hosting environments.

---

## 1) Baseline Storage Model (MySQL Only)

### 1.1 Canonical Hot Tables

Each logging domain maps to a dedicated MySQL table.

| Domain                    | MySQL Table                  | Notes                                               |
|---------------------------|------------------------------|-----------------------------------------------------|
| **Authoritative Audit**   | `authoritative_audit_outbox` | **Authoritative truth**, transactional, fail-closed |
|                           | `authoritative_audit_log`    | Materialized query table only                       |
| **Audit Trail**           | `audit_trail`                | Reads, views, exports, navigation                   |
| **Security Signals**      | `security_signals`           | Auth / policy anomalies                             |
| **Operational Activity**  | `operational_activity`       | Mutations only                                      |
| **Diagnostics Telemetry** | `diagnostics_telemetry`      | Technical observability                             |
| **Delivery Operations**   | `delivery_operations`        | Jobs, queues, notifications                         |

**Hard rule:**
Tables are **semantically isolated**. Cross-domain writes are forbidden.

---

## 2) Baseline Retention (Policy Guidance)

Retention is **configuration-driven** and domain-specific.

Baseline operation does **NOT** require automated cleanup or archiving.

Recommended starting points (non-binding defaults):

| Domain                | Suggested Retention (days) | Rationale                            |
|-----------------------|---------------------------:|--------------------------------------|
| Audit Trail           |                         90 | High volume, frequent investigations |
| Security Signals      |                        180 | Security analysis & forensics        |
| Operational Activity  |                        180 | Accountability                       |
| Diagnostics Telemetry |                         30 | Short operational value              |
| Delivery Operations   |                        180 | Reliability & provider disputes      |

**Rule:** retention policy must be adjustable without schema changes.

---

## 3) Optional Archiving Model — Mode B (MySQL → MySQL)

Note:
Any references to Mongo-based archiving (Mode A) in other documents
(e.g. ASCII overviews) are illustrative only.
This document defines the ONLY supported and approved archiving model.


> **Status:** OPTIONAL / DEFERRED  
> This is the **only supported archiving model**.

### 3.1 Why Mode B

* No dependency on MongoDB or external storage
* Preserves column-based searchability
* Easy to review, migrate, or disable
* Aligns with portability and audit requirements

---

## 4) Archive Table Design (Mode B)

### 4.1 Archive Tables

For each hot table, a mirrored archive table MAY exist:

* `audit_trail_archive`
* `security_signals_archive`
* `operational_activity_archive`
* `diagnostics_telemetry_archive`
* `delivery_operations_archive`
* *(Optional)* `authoritative_audit_log_archive`

**Rules:**

* Same columns as hot table
* Same critical indexes
* NO foreign keys
* NO behavioral logic

> **Important:**
> Even if archived, **Authoritative Audit truth remains** `authoritative_audit_outbox`.

---

## 5) Archiving Algorithm (Mode B)

Archiving is **Move + Delete inside MySQL**.

### 5.1 Stable Cursor (Hard Rule)

Ordering:

* `ORDER BY occurred_at ASC, id ASC`

Resume condition:

* `(occurred_at > :last_time)`
* OR `(occurred_at = :last_time AND id > :last_id)`

Eligibility:

* `occurred_at < :cutoff`

---

### 5.2 Required Checkpointing

Hard rule:
Checkpoint updates MUST be atomic with archive operations
to guarantee idempotency and crash safety.

Use the reserved table:

* `log_processing_checkpoints`

Fields:

* `log_stream` = hot table name
* `processor` = `archiver_mode_b`
* `last_processed_occurred_at`
* `last_processed_mysql_id`
* sanitized metadata (status, last_error)

---

### 5.3 Archiving Steps (Hard Rules)

For each eligible domain:

1. Compute cutoff using retention policy
2. Load checkpoint
3. Select batch from hot table (stable ordering)
4. Insert rows into corresponding `_archive` table
5. Verify row count equality
6. **Delete from hot table ONLY after successful insert**
7. Update checkpoint
8. Repeat until no eligible rows remain

**Hard safety rule:**
Deletion is **FORBIDDEN** unless archive insert succeeded.

---

## 6) Read Strategy (Hot + Archive)

If Mode B is enabled:

* Recent range → query hot table only
* Older range → query archive table only
* Mixed range → query both, merge by:

    * `occurred_at DESC`
    * stable cursor using `(occurred_at, id)`

---

## 7) Authoritative Audit & Archiving

Even if archive tables exist:

* **Authoritative truth** = `authoritative_audit_outbox`
* `authoritative_audit_log` and `_archive` tables are **materialized views only**
* Loss of archive data MUST NOT affect governance correctness

---

## 8) Operational Safety Policies (Binding)

### 8.1 Metadata Size Policy

* `metadata` MUST NOT exceed **64KB**
* Enforced at application layer
* Oversized metadata MUST be rejected

---

### 8.2 Timezone Policy

* `occurred_at` MUST be stored in **UTC**
* Conversion to local timezone is presentation-layer only

---

### 8.3 Retry & Failure Handling

* Archiver failures MUST be retriable
* Repeated failures MUST be visible via monitoring
* No silent data loss is allowed

---

## 9) Data Safety Rules (All Storage)

1. NEVER store secrets:

    * passwords
    * raw OTP codes
    * access tokens
    * session secrets
    * encryption keys

2. URL handling:

    * store path only
    * remove query strings
    * mask sensitive path segments if needed

3. PII minimization:

    * prefer identifiers or hashes
    * avoid raw personal data

4. Metadata discipline:

    * structured
    * minimal
    * allowlisted

---

## 10) Explicit Non-Goals

The following are **out of scope** for this architecture:

* MongoDB-based archiving
* Dual-write strategies
* MySQL partitioning
* Deleting Authoritative Audit records
* Implicit or silent archiving

---

## 11) Compliance Note

This storage and archiving model is designed to support:

* Security audits
* Compliance investigations
* GDPR-aligned retention and anonymization strategies
  *(defined outside this document)*

---

**End of Canonical Storage & Archiving Specification**
