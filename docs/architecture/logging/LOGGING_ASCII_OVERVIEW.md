# LOGGING_ASCII_OVERVIEW

> **Project:** maatify/admin-control-panel
> **Status:** CANONICAL (ASCII overview of unified logging architecture)
> **Legend Source of Truth:** `docs/architecture/logging/ASCII_FLOW_LEGENDS.md`
> **Terminology Source of Truth:** `docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md`
> **Storage Source of Truth:** `docs/architecture/logging/LOG_STORAGE_AND_ARCHIVING.md`

---

## 0) What This Document Is (And Is Not)

This file is an **ASCII-only visual overview** of the unified logging system.

* It shows **flow shapes**, **responsibility boundaries**, and **storage topology**
* It does NOT redefine terminology or storage rules
* It acts as a **visual index** tying all canonical logging documents together

If any mismatch is found between this file and:

* `ASCII_FLOW_LEGENDS.md`
* `LOG_DOMAINS_OVERVIEW.md`
* `LOG_STORAGE_AND_ARCHIVING.md`
* `GLOBAL_LOGGING_RULES.md`
* `UNIFIED_LOGGING_DESIGN.md`

Then this file MUST be updated to match them.

---

## 1) Unified Logging Pipeline (All Domains)

* Failure semantics:
  - Authoritative Audit: fail-closed (transactional outbox)
  - All other domains: fail-open (best-effort)

Each logging domain follows the same high-level pipeline shape.
Only **policy strictness** and **failure semantics** differ by domain.

```

┌──────────────────────────────────────┐
│           HTTP / UI Layer            │
│   Controllers / Middleware / Routes  │
└─────────────────────┬────────────────┘
                      │
                      v
┌──────────────────────────────────────┐
│        Domain Recorder Layer         │
│  Policy + DTO Construction + Context │
└─────────────────────┬────────────────┘
                      │
                      v
┌──────────────────────────────────────┐
│       Domain Logger / Writer         │
│   Storage Adapter (Interface/Impl)   │
└─────────────────────┬────────────────┘
                      │
                      v
┌──────────────────────────────────────┐
│            Storage Layer             │
│   MySQL Hot (Baseline)               │
│   + Optional Mongo Archive (Mode A)  │
└──────────────────────────────────────┘

```

**Canonical notes:**

* Recorder is the **only policy boundary**
* Logger/Writer is **storage-only**
* No controller or service writes logs directly
* No domain mixes with another

---

## 2) Domains (6) and Storage Targets

Canonical meanings are defined in `LOG_DOMAINS_OVERVIEW.md`.

### 2.1 Authoritative Audit (MySQL ONLY)

* Governance & security posture changes
* Fail-closed
* Authoritative pipeline (outbox → materialized log)
* Never archived in Mode A

```
┌───────────────────────────────┐
│       Authoritative Audit     │
└───────────────┬───────────────┘
                │
                v
┌──────────────────────────────────────┐
│ MySQL: authoritative_audit_outbox    │
└─────────────────────┬────────────────┘
                      │
                      v
┌──────────────────────────────────────┐
│     Outbox Consumer / Materializer   │
└─────────────────────┬────────────────┘
                      │
                      v
┌──────────────────────────────────────┐
│ MySQL: authoritative_audit_log       │
└──────────────────────────────────────┘

```

---

### 2.2 Archive-Eligible Domains (5 Domains)

These domains are **baseline-first** (MySQL) and **optionally archiveable**:

* Audit Trail
* Security Signals
* Operational Activity
* Diagnostics Telemetry
* Delivery Operations

Note:
This diagram illustrates Mode A (MySQL → Mongo) for visualization purposes.
Mode B (MySQL → MySQL archive tables) is equally canonical and defined in
LOG_STORAGE_AND_ARCHIVING.md, but omitted here for visual simplicity.

```

┌───────────────────────────────┐
│   MySQL Hot Table (Baseline)  │
└───────────────┬───────────────┘
                │
                │   (optional Mode A)
                ├──────────────────────────────▶
                │                               ┌──────────────────────────────────┐
                │                               │ Mongo Archive (Quarter Collection) │
                │                               └──────────────────────────────────┘
                │
                v
┌───────────────────────────────┐
│   MySQL Delete After Success  │
└───────────────────────────────┘

```

**Hard rule:**
Delete from MySQL is FORBIDDEN unless Mongo write succeeded.

---

## 3) Detailed Domain Flow Maps

### 3.1 Audit Trail (Data Exposure & Navigation)

```

HTTP / UI
   │
   v
AuditTrailRecorder
   │
   v
AuditTrailLogger
   │
   ├──▶ MySQL : audit_trail
   │
   └──▶ Mongo : audit_trail_YYYYqN
                (optional Mode A)

```

---

### 3.2 Security Signals

```

HTTP / UI + Domain Services
   │
   v
SecuritySignalsRecorder
   │
   v
SecuritySignalsLogger
   │
   ├──▶ MySQL : security_signals
   │
   └──▶ Mongo : security_signals_YYYYqN
                (optional Mode A)

```

---

### 3.3 Operational Activity (Mutations Only)

```

HTTP / UI
   │
   v
OperationalActivityRecorder
   │
   v
OperationalActivityLogger
   │
   ├──▶ MySQL : operational_activity
   │
   └──▶ Mongo : operational_activity_YYYYqN
                (optional Mode A)

```

---

### 3.4 Diagnostics Telemetry (Tech Observability)

```

Middleware / Instrumentation / HTTP
   │
   v
DiagnosticsTelemetryRecorder
   │
   v
DiagnosticsTelemetryLogger
   │
   ├──▶ MySQL : diagnostics_telemetry
   │
   └──▶ Mongo : diagnostics_telemetry_YYYYqN
                (optional Mode A)

```

---

### 3.5 Delivery Operations (Jobs / Notifications / Webhooks)

```

Queue / Job / Notifier
   │
   v
DeliveryOperationsRecorder
   │
   v
DeliveryOperationsLogger
   │
   ├──▶ MySQL : delivery_operations
   │
   └──▶ Mongo : delivery_operations_YYYYqN
                (optional Mode A)

```

---

### 3.6 Authoritative Audit (Compliance-Grade)

```

Domain Policy (Governance / Posture Change)
   │
   v
AuthoritativeAuditRecorder
   │
   v
Outbox Writer
   │
   v
MySQL : authoritative_audit_outbox
   │
   v
Outbox Consumer / Materializer
   │
   v
MySQL : authoritative_audit_log

```

---

## 4) Read Strategy Overview

### 4.1 Baseline (No Archiving Enabled)

```
Request Range
|
v
MySQL (hot tables only)
|
v
Response
```

---

### 4.2 Mode A Enabled (Hot + Cold)

```

Request Range
   │
   ├──▶ Hot Only   ─────────────▶ MySQL
   │
   ├──▶ Cold Only  ─────────────▶ Mongo
   │
   └──▶ Mixed      ─────────────▶ MySQL + Mongo
                                     │
                                     v
                                   Merge
                                     │
                                     v
                                  Response

```

---

## 5) Real-World Mapping Appendix (Non-Blocking, Clarifying)

This appendix exists to **reduce ambiguity for reviewers and new developers**.
It does NOT introduce new rules.

### Example A — `login_failed`

* **Domain:** Security Signals
* **Why:** Observational auth anomaly
* **NOT:** Authoritative Audit (no posture change)

---

### Example B — `create_admin`

This is **TWO distinct events**:

1. **Authoritative Audit**

   * Intent: governance / privileged account creation
2. **Operational Activity**

   * Intent: operational record of entity creation

They MUST be logged as **two separate events**, never merged.

---

### Example C — `export_customer_report`

* **Domain:** Audit Trail
* **Why:** Data exposure
* **NOT:** Operational Activity
* **NOT:** Diagnostics Telemetry

---

## 6) Glossary (Canonical Clarification)

### Audit Trail vs Authoritative Audit

* **Audit Trail**

   * Answers: *Who saw what?*
   * Concern: data exposure
   * Non-authoritative
   * Reads / views / exports

* **Authoritative Audit**

   * Answers: *What changed governance or security posture?*
   * Concern: compliance & authority
   * Authoritative source of truth
   * Mutations with legal / security weight

---

### Security Signals vs Operational Activity

* **Security Signals**

   * Observations, denials, failures
   * Best-effort
   * Never changes system state

* **Operational Activity**

   * Successful mutations
   * Day-to-day admin operations
   * No reads, no failures

---

## 7) Visual Hard Prohibitions (Reminder)

```

Diagnostics Telemetry  ───▶ Authoritative Audit tables   (FORBIDDEN)
Operational Activity   ───▶ Views / Reads / Exports      (FORBIDDEN)
Audit Trail            ───▶ Mutations                    (FORBIDDEN)
Infrastructure         ───▶ swallow                      (FORBIDDEN)
Same intent            ───▶ Multiple domains             (FORBIDDEN)

```

---

## 8) Canonical Closing Statement

This file is a **visual index**, not a rulebook.

> If a rule is not defined in the source documents,
> it does not gain authority by appearing here.

All authority remains with:

* `LOG_DOMAINS_OVERVIEW.md`
* `GLOBAL_LOGGING_RULES.md`
* `UNIFIED_LOGGING_DESIGN.md`

**END OF FILE**
