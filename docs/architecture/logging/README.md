# Logging Architecture — Index

This directory contains the **canonical architecture and specifications**
for the Unified Logging System.

Not all documents have the same authority level.
This index defines **what is binding**, **what is supporting**, and
**what is reference-only**.

---

## 🔴 Source of Truth (Authoritative & Binding)

These documents are the **single source of truth** for the logging system.
In case of any conflict, **they always win**.

- 📘 **Unified Logging System — Arabic (Canonical)**
    - [`unified-logging-system.ar.md`](./unified-logging-system.ar.md)

- 📘 **Unified Logging System — English (Canonical)**
    - [`unified-logging-system.en.md`](./unified-logging-system.en.md)

These documents define:
- The 6 canonical logging domains
- The One-Domain Rule (strict)
- Security and data-safety rules
- Storage and archiving guarantees
- Operational policies (retries, limits, sanitization)

---

## 🟠 Canonical Supporting Specifications (Binding, Subordinate)

These documents **must comply** with the Unified Logging System.
They provide detailed rules and implementation guidance but
**must not redefine semantics**.

- **Unified Logging Design**
    - [`UNIFIED_LOGGING_DESIGN.md`](./UNIFIED_LOGGING_DESIGN.md)

- **Global Logging Rules**
    - [`GLOBAL_LOGGING_RULES.md`](./GLOBAL_LOGGING_RULES.md)

- **Canonical Logger Design Standard**
    - [`CANONICAL_LOGGER_DESIGN_STANDARD.md`](./CANONICAL_LOGGER_DESIGN_STANDARD.md)

- **Log Domains Overview**
    - [`LOG_DOMAINS_OVERVIEW.md`](./LOG_DOMAINS_OVERVIEW.md)

- **Log Storage and Archiving**
    - [`LOG_STORAGE_AND_ARCHIVING.md`](./LOG_STORAGE_AND_ARCHIVING.md)

If any inconsistency exists, the **Source of Truth documents override them**.

---

## 🟢 Reference & Visualization Documents (Non-Binding)

These documents are provided for understanding, visualization,
and future library extraction.
They are **not authoritative**.

- **Logging ASCII Overview**
    - [`LOGGING_ASCII_OVERVIEW.md`](./LOGGING_ASCII_OVERVIEW.md)

- **ASCII Flow Legends**
    - [`ASCII_FLOW_LEGENDS.md`](./ASCII_FLOW_LEGENDS.md)

- **Logging Library Structure (Canonical Reference)**
    - [`LOGGING_LIBRARY_STRUCTURE_CANONICAL.md`](./LOGGING_LIBRARY_STRUCTURE_CANONICAL.md)

---

## Canonical Blueprints

The following documents define **authoritative architectural blueprints**
that all logging modules MUST follow:

- **LOGGING_MODULE_BLUEPRINT.md**
  - Defines the universal, library-grade standard for building logging modules.
  - Covers recorder ownership, policy isolation, DTO contracts, fail-open semantics,
    primitive readers, and UI separation rules.
  - This blueprint is mandatory for all new logging modules.

### Reference Implementation

A full, production-grade reference implementation of this blueprint exists:

- `app/Modules/DiagnosticsTelemetry`
  - See: `CANONICAL_ARCHITECTURE.md`
  - Status: Fully compliant / Library-ready


---

## 🚨 Change Policy (Critical)

Any change to:
- Logging domains
- One-Domain Rule
- Authoritative vs non-authoritative semantics
- Logged data categories
- Security or sanitization rules
- Storage or archiving guarantees

- Fail-open guarantees (Recorder exception boundary)
  - `Recorder::record()` MUST NOT throw under any condition.
  - `Throwable` MUST be caught ONLY at the Recorder boundary (top-level).
  - Swallowing is forbidden in Infrastructure / Repository / DTO layers (they MUST throw domain custom exceptions).
  - The only tolerated best-effort swallow is metadata decode corruption during read-mapping (metadata => null).


is considered an **Architectural Change**
and requires a formal review and approval.

No silent or ad-hoc changes are allowed.

---

## ✅ Status

- **Architecture:** Approved
- **Reviews:** Completed (4 independent reviews)
- **Stability:** Canonical / Source of Truth
