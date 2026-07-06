# ASCII_FLOW_LEGENDS.md

## Canonical ASCII Flow Language

**Project:** maatify/admin-control-panel
**Status:** CANONICAL (Binding — Reference Language)
**Scope:** All architecture, execution, storage, and logging diagrams
**Authority Alignment:**
This document MUST align with:

* `unified-logging-system.ar.md`
* `unified-logging-system.en.md`
* `UNIFIED_LOGGING_DESIGN.md`

If a conflict exists, the **Unified Logging System documents win**.

**Audience:** Developers, reviewers, auditors, future maintainers
**Interpretation:** ZERO-AMBIGUITY

---

## 1. Purpose

This document defines the **only allowed ASCII language** for describing:

* Execution flow
* Error propagation
* Responsibility boundaries
* Safety boundaries

Any diagram in this repository (or extracted libraries) **MUST** comply with
these legends.

❌ No alternative symbols
❌ No informal arrows
❌ No implicit meaning
❌ No interpretation by “understanding the intent”

If a diagram violates these legends → **the diagram is INVALID**.

---

## 2. Core Flow Symbols

### Vertical Flow (Execution Order)

```
|
v
```

**Meaning:**

* Execution continues downward
* Each block represents the next execution step

---

### Horizontal Naming Convention

``` 
<ClassName>::method()
```

**Meaning:**

* Explicit call
* Caller → Callee
* No hidden side effects implied

---

## 3. Error & Exception Symbols

### Exception Raised

```
X (error)
```

**Meaning:**

* An exception is raised at this point
* Execution flow is interrupted

❗ This symbol **MUST NOT** appear without a defined outcome.

---

### Exception Propagation

```
throws <ExceptionName>
```

**Meaning:**

* Exception propagates upward
* Caller is now responsible

---

### Exception Catching (Explicit)

```
catches <ExceptionName>
```

**Meaning:**

* Exception is intercepted
* Control flow continues from this boundary

---

### Swallowing (Silencing)

```
swallow
```

**Meaning:**

* Exception is intentionally silenced
* Execution continues normally

⚠️ **IMPORTANT RULE**

* `swallow` is **FORBIDDEN** inside libraries
* `swallow` is **ONLY ALLOWED** at **project policy boundaries**

---

## 4. Canonical Failure Flow (Library Level)

```

Caller
  |
  v
<Subsystem>WriterInterface::write(WriteDTO)
  |
  v
Mysql<Subsystem>Writer (PDO)
  |
  v
DB
  |
  X (error)
  |
  v
throws <Subsystem>StorageException

```

### Interpretation (Locked)

* Library code:

    * ALWAYS throws
    * NEVER swallows
    * NEVER logs silently (no PSR-3)
* Storage failure is **explicit**
* Responsibility is transferred upward

---

## 5. Canonical Safety Boundary (Project Level)

> SafeRecorder exists only at explicit domain policy boundaries, not ad-hoc helpers.

```

Application / Domain
  |
  v
Safe<Subsystem>Recorder   (PROJECT POLICY)
  |
  v
Library Writer            (throws)
  |
  X <Subsystem>StorageException
  |
  v
SafeRecorder catches
  |
  v
swallow   (optional PSR-3 warning)
  |
  v
Main application flow continues

```

### Interpretation (Locked)

* **Silence is a PROJECT decision**
* Library remains honest and strict
* PSR-3 logging (warning/error) is OPTIONAL and external
* Business flow MUST NOT break

---

## 6. Layer Responsibility Keywords

These keywords are **semantic markers** and MUST be respected.

```
Library
```

* Reusable
* Stateless
* Throws custom exceptions
* No swallow
* No PSR-3 logging

```
Domain
```

* Business logic
* May define policy
* May decide to swallow or escalate

```
SafeRecorder
```

* Explicit safety boundary
* Converts “throwing library” → “best-effort system”
* ONLY place allowed to silence exceptions

```
Application
```

* Controllers / Middleware / CLI
* Must NEVER contain persistence logic
* Must NEVER swallow storage exceptions directly

---

## 7. DTO & Data Flow Rules (Diagram Level)

```
DTO
```

Means:

* Strongly typed object
* Serializable
* Has `toArray()` or equivalent
* NO raw arrays in flow diagrams

❌ This is FORBIDDEN:

```
array
```

If data is passed → it MUST be named as a DTO.

---

## 8. Forbidden Patterns (INVALID DIAGRAMS)

### ❌ Silent Library Failure

```

LibraryWriter
  |
  X (error)
  |
  swallow

```

---

### ❌ Implicit Catch

```

X (error)
  |
  v
continue

```

---

### ❌ Array-Based Data

```
write(array $data)
```

---

### ❌ Undefined Error Outcome

```
X error
```

(with no throws / catches / swallow defined)

---

## 9. Mandatory Validation Rules

Every ASCII diagram MUST satisfy:

1. Every `X (error)` has:

    * `throws` OR
    * `catches` OR
    * `swallow`

2. `swallow` appears ONLY:

    * In Project / Domain policy layer

3. Library diagrams:

    * ALWAYS end with `throws <CustomException>`

4. No diagram relies on reader interpretation

Violation of any rule = **Architectural Error**

---

## 10. Canonical Statement

This document defines a **language**, not guidance.

> If it is not represented here,
> it does not exist architecturally.

---

**END OF FILE**
