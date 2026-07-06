# 🌐 Global Logging Rules

**Project:** maatify/admin-control-panel
**Status:** CANONICAL (Binding — Subordinate to unified-logging-system.*)
**Audience:** Backend Developers, Security Reviewers, Auditors
**Last Updated:** 2026-01

**Terminology Source of Truth:**

* `docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md`

**Architecture Source of Truth:**

* `unified-logging-system.ar.md`
* `unified-logging-system.en.md`

If a conflict exists, the Unified Logging System documents win.

---

## 1) Purpose

This document defines the **global, canonical rules** for all logging within the Admin Control Panel.

It exists to enforce:

* Strict separation of semantic intent
* Correct usage of the six canonical logging domains
* Elimination of misleading, duplicated, or polluted logs
* Long-term audit, security, and compliance correctness

Any deviation from these rules is a **critical architectural violation**.

---

## 2) Canonical Logging Domains (Authoritative)

The system recognizes **exactly six** logging domains.

| Domain                    | Purpose                                             | Authority Level   |
|---------------------------|-----------------------------------------------------|-------------------|
| **Authoritative Audit**   | Governance & security posture changes               | **Authoritative** |
| **Audit Trail**           | Data exposure: views / reads / exports / navigation | Non-authoritative |
| **Security Signals**      | Auth & policy anomalies                             | Non-authoritative |
| **Operational Activity**  | Operational mutations (CRUD, approvals)             | Non-authoritative |
| **Diagnostics Telemetry** | Technical observability & diagnostics               | Non-authoritative |
| **Delivery Operations**   | Jobs, queues, notifications, webhooks lifecycle     | Non-authoritative |

No additional logging domain is allowed.

---

## 3) One-Domain Rule (Hard Rule)

Every logged event MUST belong to **exactly one** domain based on its **primary intent**.

If a real-world action appears to involve multiple domains:

* it MUST be represented as **multiple distinct events**
* each event is logged separately in its proper domain
* the same intent MUST NOT be logged twice

Domains are **not interchangeable**.

---

## 4) Authoritative Audit

### 4.1 Definition

Authoritative Audit represents **compliance-grade, governance-critical** changes that affect:

* security posture
* authority and privileges
* access governance
* irreversible compliance-sensitive state

### 4.2 Authoritative Pipeline (Hard Rule)

Authoritative Audit MUST be written via the authoritative pipeline:

* **Authoritative source:** `authoritative_audit_outbox`
* **Materialized query table:** `authoritative_audit_log`

Rules:

* The outbox is the **only source of truth**
* Log tables are **materialized views only**
* Failures MUST propagate (fail-closed)

### 4.3 When to Use Authoritative Audit

* Privileged account creation or deletion (admins)
* Role or permission assignment / revocation
* System ownership changes
* Policy-critical session revocation
* Governance-grade security configuration changes

### 4.4 When NOT to Use Authoritative Audit

* Login failures
* Invalid credentials
* Step-up failures
* Permission denials
* Any non-governance operational event

Clarification:
Authoritative Audit is the ONLY domain that is fail-closed.
All other domains are explicitly fail-open and best-effort by design.


---

## 5) Audit Trail (Data Exposure & Navigation)

### 5.1 Definition

Audit Trail answers:

> Who accessed what data, when, and how?

It is the **only** domain for:

* reads
* views
* exports
* downloads
* navigation that exposes sensitive data

### 5.2 Storage

Audit Trail MUST be written to:

* `audit_trail` (MySQL hot table)

### 5.3 Hard Rule

Any event that represents **data exposure** MUST be logged as **Audit Trail**.

---

## 6) Security Signals

### 6.1 Definition

Security Signals are **observational risk indicators** for:

* suspicious behavior
* failed security actions
* policy violation attempts
* abuse patterns

They are **non-authoritative**.

### 6.2 Characteristics (Hard Rules)

Security Signals:

* are best-effort
* MUST NOT affect control flow
* MUST NOT be transactional
* MUST NOT block user actions

### 6.3 Storage

Security Signals MUST be written to:

* `security_signals`

### 6.4 Severity Levels

Severity reflects **risk**, not authority.

| Severity | Meaning                   |
|----------|---------------------------|
| INFO     | Informational             |
| WARNING  | Suspicious behavior       |
| ERROR    | Security-relevant failure |
| CRITICAL | High-risk incident        |

---

## 7) Operational Activity (Mutations Only)

### 7.1 Definition

Operational Activity tracks **state-changing actions** that are not governance-grade.

### 7.2 Storage

Operational Activity MUST be written to:

* `operational_activity`

### 7.3 Hard Prohibition

Operational Activity MUST NOT represent:

* views
* reads
* exports
* downloads
* navigation

**Reads are Audit Trail.
Mutations are Operational Activity.**

---

## 8) Diagnostics Telemetry

### 8.1 Definition

Diagnostics Telemetry exists **only** for technical observability:

* performance metrics
* diagnostics
* system health
* sanitized error summaries

### 8.2 Rules

Diagnostics Telemetry:

* MUST NOT represent business events
* MUST NOT represent security posture changes
* MUST tolerate failure (fail-open)

### 8.3 Storage

Diagnostics Telemetry MUST be written to:

* `diagnostics_telemetry`

---

## 9) Delivery Operations

### 9.1 Definition

Delivery Operations track **asynchronous execution lifecycle**:

* jobs
* queues
* notifications
* webhooks
* retries and outcomes

### 9.2 Storage

Delivery Operations MUST be written to:

* `delivery_operations`

---

## 10) PSR-3 Diagnostic Channel (Not a Domain)

### 10.1 Definition

PSR-3 logging is **not** a business logging domain.

It is used only for:

* infrastructure failures
* dependency outages
* swallowed exceptions at policy boundaries
* logging system failures

### 10.2 Core Rule

> PSR-3 logs describe **system problems**, not user behavior.

PSR-3 MUST NOT replace any logging domain.

Absence of a domain log is a design defect.
PSR-3 MUST NOT be used to compensate for missing or skipped domain logging.

---

## 11) Normalized Context & Safety Rules (Hard)

### 11.1 Normalized Context

All domain logs MUST include normalized context:

* `actor_type` (validated enum-like)
* `actor_id`
* `request_id` (single request scope)
* `correlation_id` (multi-request workflow scope)
* `route_name`
* `ip_address`
* `user_agent`
* `occurred_at` (**UTC only**)

### 11.2 actor_type Allowed Values

`actor_type` MUST be one of:

* SYSTEM
* ADMIN
* USER
* SERVICE
* API_CLIENT
* ANONYMOUS

Any other value is invalid.

---

## 12) Data Safety Rules (Hard)

* NEVER log secrets:

  * passwords
  * raw OTP codes
  * access tokens
  * session secrets
  * encryption keys
* URLs:

  * store path only
  * strip query strings
  * mask sensitive path segments if needed
* Metadata:

  * structured
  * minimal
  * allowlisted
  * **maximum size: 64KB**
* Logs MUST NOT be treated as a source of business truth
  (e.g. permissions, balances, ownership, or state).

---

## 13) Forbidden Patterns (Hard)

* Cross-domain logging
* Double-writing the same intent
* Using Telemetry as Audit
* Using Audit for failures
* Security Signals affecting control flow
* Operational Activity for reads
* Logging secrets
* Silent policy violations

Violations block merges and require remediation.

---

## 14) Change Policy

This document is:

* Canonical
* Version-controlled
* Changeable only via explicit architectural decision

Ad-hoc exceptions are forbidden.

---

## 15) Summary

> Logging domains are not interchangeable.

Misuse creates:

* false audit narratives
* missed security incidents
* compliance risk
* broken investigations

Follow these rules strictly.

---

**END OF GLOBAL LOGGING RULES**
