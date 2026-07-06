# LOG_DOMAINS_OVERVIEW

> **Project:** maatify/admin-control-panel
> **Status:** CANONICAL (Binding — Subordinate to unified-logging-system.* as Source of Truth)
> **Scope:** Defines the **six** logging domains, their intent, boundaries, and classification rules.
> **Authority Rule:** This document MUST fully align with:
>
> * `unified-logging-system.ar.md`
> * `unified-logging-system.en.md`
    >   If a conflict exists, the **Unified Logging System documents win**.

---

## 0) The One-Domain Rule

Every logged event MUST be classified into exactly **one** domain based on its **primary intent**.

If an event seems to fit multiple domains:

* Choose the domain that answers the **main question** the log is meant to support.
* Secondary details belong in `metadata` (sanitized) **only if** they do not represent a separate intent.
* If two intents exist, they MUST be logged as **two distinct events** in their respective domains.

Hard clarification:
Metadata MUST NOT be used to encode a second intent.
If an intent deserves its own investigation question,
it MUST be logged as a separate event in its proper domain.

**Hard rule:**

* Do NOT double-write the same intent into multiple domains.
* Multiple domains are allowed **only** when intents are truly separate.

---

## 1) Canonical Domains (6 Only)

The logging system recognizes **exactly six** domains. No additions are allowed.

### 1.1 Authoritative Audit

**Intent:** Compliance-grade, authoritative record of **security posture** and **governance-critical** changes.

**Use when you need to answer:**

* “What change affected security posture, governance, or compliance — and is this record authoritative?”

**Examples (Typical):**

* Admin created / deleted
* Role or permission assignments changed
* System ownership or privileged capability changes
* Credential posture changes (password policy, step-up policy, account lock policies)
* Key rotation or crypto governance changes (when treated as governance-critical)
* Force logout / revoke all sessions (when policy-critical)

**Not allowed (Non-examples):**

* Page visits, browsing, viewing data → **Audit Trail**
* Login failures / permission denials → **Security Signals**
* Technical errors or performance traces → **Diagnostics Telemetry**
* Email / queue / job lifecycle → **Delivery Operations**
* Routine low-risk mutations → **Operational Activity**

**Key properties:**

* Must be written through an **authoritative pipeline** (e.g., outbox → materialized log).
* **Source of truth:** `authoritative_audit_outbox` (transactional).
* Log tables are materialized views only.
* Must be minimal, structured, and safe (no secrets).
* Must be **fail-closed**: the governed change MUST NOT commit without a successful outbox write.

This is the ONLY logging domain that is fail-closed.
All other domains are best-effort and fail-open by design.

---

### 1.2 Audit Trail (Data Exposure & Navigation)

**Intent:** Traceability of **reads, views, exports, and navigation** representing **data exposure**.

**Use when you need to answer:**

* “Who opened what? Who viewed whose data? What did they export? Where did they navigate?”

**Examples (Typical):**

* Viewed customer profile
* Opened an order details page
* Searched customer data (when results contain sensitive records)
* Exported records (CSV / PDF)
* Downloaded an attachment
* Visited a page that reveals sensitive information

**Not allowed (Non-examples):**

* Any state change (create/update/delete) → **Operational Activity**
  *(or **Authoritative Audit** if governance-critical)*
* Login failures / permission denied → **Security Signals**
* Performance metrics → **Diagnostics Telemetry**
* Notification or job lifecycle → **Delivery Operations**

**Key properties:**

* MUST support “data exposure” investigations.
* URLs MUST be sanitized:

  * Path only (no query strings)
  * Mask or redact sensitive path segments (tokens, secrets).
* Should support subject tracking where relevant (e.g., whose data was viewed).

---

### 1.3 Security Signals

**Intent:** Security-relevant signals for monitoring, alerting, and risk investigation.

> **Terminology note:** Legacy references may call these “Security Events”.
> Canonical domain name is **Security Signals** (non-authoritative).

**Use when you need to answer:**

* “Was there an authentication, authorization, or policy anomaly or denial?”

**Examples (Typical):**

* Login failed / blocked / throttled
* Permission denied
* Session invalid or expired anomalies
* Step-up (2FA) failed, invalid code, risk mismatch, not enrolled
* Recovery or sensitive action blocked by policy
* Suspicious token usage signals (best-effort, no secrets)

**Not allowed (Non-examples):**

* Data views or exports → **Audit Trail**
* Routine operational edits → **Operational Activity**
* Governance-grade posture changes → **Authoritative Audit**
* Technical performance or stack traces → **Diagnostics Telemetry**
* Delivery attempts or retries → **Delivery Operations**

**Key properties:**

* Must include severity (INFO / WARNING / ERROR / CRITICAL).
* Must use stable reason codes and safe metadata.
* **Best-effort and fail-open** (must not affect control flow).

---

### 1.4 Operational Activity (Mutations & Operations)

**Intent:** Operational record of **state changes** and administrative actions that are **not** governance-grade authoritative audit.

**Use when you need to answer:**

* “Who changed what in day-to-day operations?”

**Examples (Typical):**

* Updated customer record (non-governance fields)
* Created or edited content pages
* Updated non-security settings
* Approved or rejected operational requests
* CRUD actions for standard admin entities

**Hard rules:**

* **Reads are forbidden.**
* If it is a read/view/export/navigation → **Audit Trail**.

**Not allowed (Non-examples):**

* Login failures / permission denied → **Security Signals**
* Governance-critical changes → **Authoritative Audit**
* Diagnostics or performance → **Diagnostics Telemetry**
* Delivery or job lifecycle → **Delivery Operations**

> **Reference Implementation (Library):**
>
> The canonical reference library for this domain is:
>
> **BehaviorTrace**
>
> - Purpose: Non-authoritative tracing of operational mutations and day-to-day actions.
> - Classification: Maps **strictly** to the *Operational Activity* domain.
> - Constraints:
    >   - Mutations only (no reads).
>   - Non-governance, non-security.
>   - Fail-open, side-effect free.
> - This library MUST NOT be used for:
    >   - Audit Trail
>   - Security Signals
>   - Authoritative Audit
>   - Diagnostics Telemetry
> - Hard rule:
      BehaviorTrace MUST NEVER be used as a fallback or “generic logger”.
      Using it outside Operational Activity classification is a violation.


---

### 1.5 Diagnostics Telemetry (Technical Observability)

**Intent:** Technical observability: performance, tracing, instrumentation, and sanitized error summaries.

**Use when you need to answer:**

* “What happened technically? Where is latency? Which subsystem failed?”

**Examples (Typical):**

* Request duration / controller timing
* DB slow query summaries (no sensitive parameters)
* Cache hit / miss
* Internal exception summaries (sanitized)
* Rate limiter metrics (counts only)

**Not allowed (Non-examples):**

* Data exposure → **Audit Trail**
* Auth failures or denials → **Security Signals**
* Mutations or ops records → **Operational Activity**
* Governance-critical logs → **Authoritative Audit**
* Delivery attempts or retries → **Delivery Operations**

**Key properties:**

* Avoid PII; use identifiers or hashes where needed.
* Never log secrets or raw tokens.
* Best-effort and fail-open.

---

### 1.6 Delivery Operations (Jobs, Queues, Notifications, Webhooks)

**Intent:** Lifecycle, reliability, and troubleshooting of asynchronous operations and delivery channels.

**Use when you need to answer:**

* “Was the job or notification delivered? How many retries occurred? What provider error happened?”

**Examples (Typical):**

* Email queued / sent / delivered / failed
* SMS / Telegram push attempts and retries
* Webhook delivery attempts and responses
* Background job started / finished and result
* Provider message IDs and sanitized error codes

**Not allowed (Non-examples):**

* Login failures / permission denied → **Security Signals**
* Data exposure → **Audit Trail**
* CRUD or mutations → **Operational Activity**
* Performance or tracing → **Diagnostics Telemetry**
* Governance-critical changes → **Authoritative Audit**

**Key properties:**

* Track status, attempt counters, timestamps.
* Never store secrets; store provider IDs and sanitized responses only.

---

## 2) PSR-3 Diagnostic Channel (NOT a Domain)

The PSR-3 logger is a **diagnostic channel**, not a business logging domain.

It is used for:

* Infrastructure failures
* Best-effort write failures (e.g., telemetry write failed)
* Unexpected runtime conditions
* Exceptions caught and intentionally swallowed

**Hard rule:**

* PSR-3 MUST NOT replace or duplicate any of the six domains.

PSR-3 logs MUST NEVER be used as a substitute for missing domain logs.
Absence of a domain log is a design error, not a PSR-3 use case.

---

## 3) Canonical Classification Guide (Quick Table)

| Scenario                            | Domain                                                                 |
|-------------------------------------|------------------------------------------------------------------------|
| Admin role/permission changed       | Authoritative Audit                                                    |
| Admin viewed customer profile       | Audit Trail                                                            |
| Admin exported customer list        | Audit Trail                                                            |
| Login failed / blocked / throttled  | Security Signals                                                       |
| Permission denied                   | Security Signals                                                       |
| Admin updated customer data         | Operational Activity *(or Authoritative Audit if governance-critical)* |
| Request took 3.2s / DB slow         | Diagnostics Telemetry                                                  |
| Email delivery failed after retries | Delivery Operations                                                    |

---

## 4) Cross-Contamination Rules (Hard Rules)

1. Audit Trail is the **only** domain for reads/views/exports/navigation.
2. Operational Activity is **mutations only** (no reads).
3. Security Signals are **only** for security anomalies/denials/failures.
4. Diagnostics Telemetry is **only** for technical observability.
5. Delivery Operations is **only** for job/queue/delivery lifecycles.
6. Authoritative Audit is **only** for governance and security posture changes.
7. PSR-3 is **not** a domain.

---

## 5) Data Safety Rules (Applies to ALL Domains)

* NEVER log secrets:

  * passwords, raw OTP codes, access tokens, session secrets, encryption keys
* URLs:

  * store path only
  * remove query strings
  * mask sensitive path segments (tokens, secrets)
* PII minimization:

  * prefer identifiers, hashes, or stable keys
* Metadata discipline:

  * structured, minimal, allowlisted where possible
  * **maximum size: 64KB** (enforced at application layer)
* Prefer stable taxonomy keys:

  * `event_key`, `signal_type`, `action`, `operation_type`

---

## 6) Normalized Context Requirements (Alignment)

* `occurred_at` MUST be stored in **UTC**.
* Timezone conversion is handled at the presentation layer.
* `request_id` identifies a **single HTTP request**.
* `correlation_id` links **multiple requests** within one business workflow.

---

## 7) actor_type Allowed Values

`actor_type` MUST be validated at the application layer and restricted to:

* SYSTEM
* ADMIN
* USER
* SERVICE
* API_CLIENT
* ANONYMOUS

Any value outside this set is invalid.

---

## 8) Storage Reference

Storage rules, retention guidance, and archiving mechanics are defined in:

* `docs/architecture/logging/LOG_STORAGE_AND_ARCHIVING.md`

**Baseline note:** Archiving is OPTIONAL and not required for the baseline schema.
