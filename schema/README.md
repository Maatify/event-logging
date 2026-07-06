# Event Logging Schema Layout

The package keeps SQL schema files beside the domain that owns them instead of duplicating or flattening them into this directory.

## Domain-local schema files

- `src/AuthoritativeAudit/Database/schema.authoritative_audit.sql`
- `src/AuditTrail/Database/schema.audit_trail.sql`
- `src/SecuritySignals/Database/schema.security_signals.sql`
- `src/BehaviorTrace/Database/schema.behavior_trace.sql`
- `src/DiagnosticsTelemetry/Database/schema.diagnostics_telemetry.sql`
- `src/DeliveryOperations/Database/schema.delivery_operations.sql`

## Rationale

Each logging domain has an independent storage model and failure policy. Keeping each schema under its owning domain makes ownership explicit, avoids implying a shared generic log table, and preserves domain-local review of indexes, comments, retention rules, and table policies.

This directory exists only as the package-level schema index expected by the module standard. It intentionally does not duplicate SQL files, because duplicate schema copies can drift from the authoritative domain-local files.
