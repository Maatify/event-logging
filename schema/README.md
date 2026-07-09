# Event Logging Schema Layout

For the canonical storage guarantees, deferred archiving plans, and query semantics, please see the [Storage & Schema Guarantees](../docs/architecture/STORAGE_AND_SCHEMA.md) documentation.

The package keeps SQL schema files beside the domain that owns them instead of duplicating or flattening them into this directory.

## Domain-local schema files

- `src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql`
- `src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql`
- `src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql`
- `src/BehaviorTrace/Database/schema.maa_event_logging_behavior_trace.sql`
- `src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql`
- `src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql`


## Canonical table names

| Domain | Schema file | Table |
| --- | --- | --- |
| AuthoritativeAudit | `src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql` | `maa_event_logging_authoritative_audit_outbox`, `maa_event_logging_authoritative_audit_log` |
| AuditTrail | `src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql` | `maa_event_logging_audit_trail` |
| SecuritySignals | `src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql` | `maa_event_logging_security_signals` |
| BehaviorTrace | `src/BehaviorTrace/Database/schema.maa_event_logging_behavior_trace.sql` | `maa_event_logging_behavior_trace` |
| DiagnosticsTelemetry | `src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql` | `maa_event_logging_diagnostics_telemetry` |
| DeliveryOperations | `src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql` | `maa_event_logging_delivery_operations` |

## Rationale

Each logging domain has an independent storage model and failure policy. Keeping each schema under its owning domain makes ownership explicit, avoids implying a shared generic log table, and preserves domain-local review of indexes, comments, retention rules, and table policies.

This directory exists only as the package-level schema index expected by the module standard. It intentionally does not duplicate SQL files, because duplicate schema copies can drift from the authoritative domain-local files.
