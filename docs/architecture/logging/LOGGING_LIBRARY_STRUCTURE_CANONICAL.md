# LOGGING_LIBRARY_STRUCTURE_CANONICAL

> **Project:** maatify/admin-control-panel
> **Status:** CANONICAL (Structural blueprint for logging modules as future standalone libraries)
> **Scope:** Defines the required folder structure, module boundaries, shared primitives, and extraction-ready layout for the six logging domains.
> **Terminology Source of Truth:** `docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md`
> **Design Standard Source of Truth:** `docs/architecture/logging/CANONICAL_LOGGER_DESIGN_STANDARD.md`

---

## 0) Purpose

This document enforces a single structural rule:

> Every logging domain MUST be structured as if it will be extracted into an independent library later.

This prevents:

* “one mega logger”
* cross-domain coupling
* hidden policy in infrastructure
* ad-hoc DTO shapes

---

## 1) Canonical Domains (6)

The system has exactly six logging domains:

1. Authoritative Audit
2. Audit Trail
3. Security Signals
4. Operational Activity
5. Diagnostics Telemetry
6. Delivery Operations

Definitions are canonical in:

* `docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md`

---

## 2) Hard Structural Rules

1. **One module per domain.**
2. **No shared storage drivers across domains.**
3. **No domain policy inside infrastructure.**
4. **No raw arrays as public inputs.**
5. All DTO class names MUST end with `DTO`.
6. All Enum names MUST end with `Enum`.
7. “Swallowing” is allowed ONLY at the Recorder boundary and ONLY for
   Non-Authoritative domains.
   The Recorder MUST catch `Throwable` at the top-level of `record()`
   to guarantee fail-open behavior.
   Swallowing inside Infrastructure, Repository, DTO, or Policy layers
   is strictly forbidden.

---

## 3) Canonical In-Repo Module Layout

All domain modules live under:

```
app/Modules/
```

Required structure:

```
app/Modules/<DomainName>/
Contract/
DTO/
Enum/
Recorder/
Infrastructure/
    Mysql/
    Mongo/
Exception/

┌──────────────────────────────────────────────────────────────┐
│                      app/Modules/                            │
└──────────────────────────────────────────────────────────────┘
              |
              v
┌──────────────────────────────────────────────────────────────┐
│                 <DomainName> Module                          │
└──────────────────────────────────────────────────────────────┘
              |
              v
┌────────────┬────────────┬────────────┬────────────┬──────────┐
│ Contract/  │   DTO/     │   Enum/    │ Recorder/  │ Exception│
└────────────┴────────────┴────────────┴────────────┴──────────┘
              |
              v
┌──────────────────────────────────────────────────────────────┐
│                     Infrastructure/                          │
└──────────────────────────────────────────────────────────────┘
              |
              v
        ┌───────────────┬─────────────────────────────────────┐
        │    Mysql/     │              Mongo/                 │
        │ (Baseline)    │      (Archive-Eligible Domains)     │
        └───────────────┴─────────────────────────────────────┘

        |
        v
┌───────────────────────────────────────────────────────────┐
│                    Exception/                             │
└───────────────────────────────────────────────────────────┘

```

Notes:

* `Mongo/` is required only for archive-eligible domains (see storage doc).
* `Exception/` is mandatory for honest contracts.

---

## 4) Domain Modules (Exact Names + Responsibility)

### 4.1 Authoritative Audit Module

**Domain intent:** Compliance-grade governance/security posture changes.
**Storage:** MySQL only (outbox + materialized audit log).

```
app/Modules/AuthoritativeAudit/
    Contract/
        AuthoritativeAuditOutboxWriterInterface.php
        AuthoritativeAuditLogReaderInterface.php
    DTO/
        AuthoritativeAuditOutboxWriteDTO.php
        AuthoritativeAuditLogViewDTO.php
    Enum/
        AuthoritativeAuditRiskLevelEnum.php
        AuthoritativeAuditActorTypeEnum.php
    Recorder/
        AuthoritativeAuditRecorder.php
    Infrastructure/
        Mysql/
            AuthoritativeAuditOutboxWriterMysqlRepository.php
            AuthoritativeAuditLogReaderMysqlRepository.php
    Exception/
        AuthoritativeAuditStorageException.php
        
┌──────────────────────────────────────────────────────────────┐
│        Authoritative Audit (Compliance-Grade Flow)           │
└──────────────────────────────────────────────────────────────┘
              |
              v
AuthoritativeAuditRecorder
              |
              v
AuthoritativeAuditOutboxWriterInterface
              |
              v
MySQL: authoritative_audit_outbox   [AUTHORITATIVE SOURCE]
              |
              v
Outbox Consumer / Materializer
              |
              v
MySQL: authoritative_audit_log      [QUERY / READ MODEL]


```

Hard rule:

* The outbox writer is part of the authoritative pipeline.
* No Mongo archive.

---

### 4.2 Audit Trail Module

**Domain intent:** Data exposure + navigation (views/reads/exports/downloads).
**Storage:** MySQL hot + Mongo archive.

```
app/Modules/AuditTrail/
    Contract/
        AuditTrailLoggerInterface.php
        AuditTrailQueryInterface.php
    DTO/
        AuditTrailRecordDTO.php
        AuditTrailQueryDTO.php
        AuditTrailViewDTO.php
    Enum/
        AuditTrailActorTypeEnum.php
        AuditTrailEventKeyEnum.php (optional; taxonomy may be string-based if too broad)
    Recorder/
        AuditTrailRecorder.php
    Infrastructure/
        Mysql/
            AuditTrailLoggerMysqlRepository.php
            AuditTrailQueryMysqlRepository.php
        Mongo/
            AuditTrailArchiveMongoRepository.php
    Exception/
        AuditTrailStorageException.php
```

Hard rule:

* Any view/read/export belongs here, never in Operational Activity.

---

### 4.3 Security Signals Module

**Domain intent:** Auth/authorization anomalies, policy violations, suspicious signals.
**Storage:** MySQL hot + Mongo archive.

```
app/Modules/SecuritySignals/
    Contract/
        SecuritySignalsLoggerInterface.php
        SecuritySignalsQueryInterface.php
    DTO/
        SecuritySignalRecordDTO.php
        SecuritySignalsQueryDTO.php
        SecuritySignalViewDTO.php
    Enum/
        SecuritySignalTypeEnum.php
        SecuritySignalSeverityEnum.php
        SecuritySignalActorTypeEnum.php
    Recorder/
        SecuritySignalsRecorder.php
    Infrastructure/
        Mysql/
            SecuritySignalsLoggerMysqlRepository.php
            SecuritySignalsQueryMysqlRepository.php
        Mongo/
            SecuritySignalsArchiveMongoRepository.php
    Exception/
        SecuritySignalsStorageException.php
```

Hard rule:

* “Permission denied”, “login failed”, “session invalid” belong here.

---

### 4.4 Operational Activity Domain — BehaviorTrace Module

**Domain intent:** Mutations + operational actions (create/update/delete/approve/etc).
**Storage:** MySQL hot + Mongo archive.

> **Library / Module Name:** BehaviorTrace  
> **Domain Classification:** Operational Activity  
> **Authoritative Source:** LOG_DOMAINS_OVERVIEW.md

```
app/Modules/BehaviorTrace/
    Contract/
        BehaviorTraceLoggerInterface.php
        BehaviorTraceQueryInterface.php
    DTO/
        BehaviorTraceRecordDTO.php
        BehaviorTraceQueryDTO.php
        BehaviorTraceViewDTO.php
    Enum/
        BehaviorTraceActorTypeEnum.php
        BehaviorTraceActionEnum.php (optional; may be string taxonomy)
    Recorder/
        BehaviorTraceRecorder.php
    Infrastructure/
        Mysql/
            BehaviorTraceLoggerMysqlRepository.php
            BehaviorTraceQueryMysqlRepository.php
        Mongo/
            BehaviorTraceArchiveMongoRepository.php
    Exception/
        BehaviorTraceStorageException.php
```

Hard rule:

* Reads/views/exports are forbidden here.

---

### 4.5 Diagnostics Telemetry Module

**Domain intent:** Technical observability (timings, sanitized errors, counters).
**Storage:** MySQL hot + Mongo archive.

```
app/Modules/DiagnosticsTelemetry/
    Contract/
        DiagnosticsTelemetryLoggerInterface.php
        DiagnosticsTelemetryQueryInterface.php
    DTO/
        DiagnosticsTelemetryRecordDTO.php
        DiagnosticsTelemetryQueryDTO.php
        DiagnosticsTelemetryViewDTO.php
    Enum/
        DiagnosticsTelemetrySeverityEnum.php
    Recorder/
        DiagnosticsTelemetryRecorder.php
    Infrastructure/
        Mysql/
            DiagnosticsTelemetryLoggerMysqlRepository.php
            DiagnosticsTelemetryQueryMysqlRepository.php
        Mongo/
            DiagnosticsTelemetryArchiveMongoRepository.php
    Exception/
        DiagnosticsTelemetryStorageException.php
```

Hard rule:

* Must avoid PII/secrets.
* Never used for data access tracking.

---

### 4.6 Delivery Operations Module

**Domain intent:** Job/queue/notification/webhook lifecycle + retries + provider results.
**Storage:** MySQL hot + Mongo archive.

```
app/Modules/DeliveryOperations/
    Contract/
        DeliveryOperationsLoggerInterface.php
        DeliveryOperationsQueryInterface.php
    DTO/
        DeliveryOperationRecordDTO.php
        DeliveryOperationsQueryDTO.php
        DeliveryOperationViewDTO.php
    Enum/
        DeliveryChannelEnum.php
        DeliveryStatusEnum.php
        DeliveryOperationTypeEnum.php
        DeliverySeverityEnum.php (optional; if needed)
    Recorder/
        DeliveryOperationsRecorder.php
    Infrastructure/
        Mysql/
            DeliveryOperationsLoggerMysqlRepository.php
            DeliveryOperationsQueryMysqlRepository.php
        Mongo/
            DeliveryOperationsArchiveMongoRepository.php
    Exception/
        DeliveryOperationsStorageException.php
```

Hard rule:

* Only delivery lifecycle belongs here (queued/sent/failed/retry/provider ids).
* Not used for auth failures or data exposure.

---

## 5) Shared Primitives (Allowed Shared Module)

A small shared module is allowed ONLY for generic primitives that do not encode domain meaning:

```
app/Modules/LoggingCommon/
    Correlation/
        CorrelationId.php
        RequestId.php
    Actor/
        ActorTypeEnum.php (only if truly shared and identical)
    Sanitization/
        UrlSanitizer.php
        MetadataSanitizer.php
    Clock/
        ClockInterface.php


┌───────────────────────────────────────────────────────────┐
│                 Shared Primitives Boundary                │
└───────────────────────────────────────────────────────────┘
        |
        v
LoggingCommon
        |
        +───────────+───────────+───────────+─────────────+
        |           |           |           |             |
        v           v           v           v
Correlation/     Actor/     Sanitization/   Clock/
(CorrelationId) (ActorType) (Url/Metadata) (ClockInterface)

```
Important:
- A shared ActorTypeEnum MAY exist ONLY if its allowed values are
  strictly identical to the canonical actor_type list.
- Domain-specific ActorTypeEnums MUST NOT extend or override semantics.
- If a domain requires additional actor classification, it MUST use
  domain-specific fields, not actor_type.

Hard rule:

* LoggingCommon MUST NOT contain domain-specific rules.
* No storage code here.
* No “generic log event” DTO here.

---

## 6) Extraction Mapping (Future Library Targets)

This structure is extraction-ready. Each domain maps cleanly to a package:

* `maatify/authoritative-audit`
* `maatify/audit-trail`
* `maatify/security-signals`
* `maatify/operational-activity`
* `maatify/diagnostics-telemetry`
* `maatify/delivery-operations`
* `maatify/logging-common` (optional; keep minimal)

```

┌───────────────────────────────────────────────────────────┐
│               Extraction-Ready Package Mapping            │
└───────────────────────────────────────────────────────────┘
AuthoritativeAudit                      → maatify/authoritative-audit
AuditTrail                              → maatify/audit-trail
SecuritySignals                         → maatify/security-signals
OperationalActivity (BehaviorTrace)     → maatify/behavior-trace
DiagnosticsTelemetry                    → maatify/diagnostics-telemetry
DeliveryOperations                      → maatify/delivery-operations
LoggingCommon                           → maatify/logging-common

```

---

## 7) Minimum Interfaces (Canonical)

Every domain module MUST expose at least:

1. `Recorder` (policy boundary)
2. `LoggerInterface` (write contract)
3. `QueryInterface` (read contract, optional for telemetry depending on UI needs)
4. `StorageException` (domain-specific)

The public write API MUST accept only a `...RecordDTO` (no arrays).

---

## Read-Side Corruption Tolerance (Explicit Exception)

- Query/Reader implementations MAY swallow JSON decode errors
  for `metadata` fields ONLY during read-mapping.
- In case of corruption, `metadata` MUST be set to `null`
  and the record MUST still be returned.
- No other swallowing is permitted on the read-side.

---

## 8) Enforcement Summary

A logging module violates this document if any of the following occurs:

* A domain module writes to another domain’s table/collection.
* A driver swallows exceptions.
* A public API accepts raw arrays.
* A recorder performs SQL/Mongo operations.
* Views/reads/exports are logged outside Audit Trail.
* Telemetry is used to represent business access events.

---

## 9) Explicit Non-Goals

This document does NOT define:
- Database schema details
- Retention periods
- Indexing strategies
- Query optimization rules
- Business-level logging decisions

Those are defined in their respective canonical documents.
