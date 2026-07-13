# Event Logging Release Readiness Roadmap

> **Status Notice:** This is a completed historical roadmap for the preparation of the `v1.0.0` release baseline. It is not the current post-v1 Admin Query execution roadmap. Current post-v1 work is governed by `ADMIN_QUERY_API_ROADMAP.md`.

Current Status

Historical PASS / Released

Phase 3 and 4 status:

* Completed. All validation commands and code consistency passed.

Phase 1 status:

* Completed on 2026-07-06.
* Database table names now follow the Maatify module naming convention.
* Required table prefix: maa_event_logging_*

Phase 1 — Fix Database Naming

Status: Completed.

Applied table renames:

| Previous table | Current table |
| --- | --- |
| `authoritative_audit_outbox` | `maa_event_logging_authoritative_audit_outbox` |
| `authoritative_audit_log` | `maa_event_logging_authoritative_audit_log` |
| `audit_trail` | `maa_event_logging_audit_trail` |
| `security_signals` | `maa_event_logging_security_signals` |
| `operational_activity` | `maa_event_logging_behavior_trace` |
| `diagnostics_telemetry` | `maa_event_logging_diagnostics_telemetry` |
| `delivery_operations` | `maa_event_logging_delivery_operations` |

Completed updates:

* SQL schema files now create the prefixed tables.
* MySQL repositories now insert into/query the prefixed tables.
* Index names were shortened and made event-logging-specific where needed to stay under MySQL identifier limits and avoid ambiguity.
* Schema comments were reviewed for outdated cross-table references.
* `schema/README.md` continues to index the domain-local schema files.
* Public/internal documentation with table-name references was updated where needed.

Phase 2 — Documentation Cleanup & Schema Artifact Alignment

Status: Completed.

Actions taken:
* Schema SQL artifact filenames were renamed to match canonical `maa_event_logging_*` table names.
* Confirmed no PHP runtime code changed in Phase 2; this was a documentation/schema artifact alignment only.

Cleaned files:
* README.md
* EVENT_LOGGING_PACKAGE_REFERENCE.md
* TESTING_STRATEGY.md
* CHANGELOG.md
* docs/audits/FULL_ARCHITECTURE_AUDIT.md
* docs/audits/AUDIT_REPORT.md
* schema/README.md
* src/DeliveryOperations/README.md
* src/DiagnosticsTelemetry/CHECKLIST.md
* src/DiagnosticsTelemetry/TESTING_STRATEGY.md
* src/DiagnosticsTelemetry/README.md
* src/AuthoritativeAudit/README.md
* src/BehaviorTrace/README.md
* src/AuditTrail/README.md
* src/SecuritySignals/README.md

Rules:

* No incorrect dependency claims.
* No host application history.
* No outdated table names.
* CI wording must match actual workflow behavior.
* Public docs must be suitable for release.

Phase 3 — Code Consistency Review

Status: Completed.

Verify:

* Commands are public input contracts.
* DTOs are read/output or internal recorder-to-writer transfer objects.
* Non-authoritative recorders are fail-open.
* AuthoritativeAudit remains fail-closed.
* No GenericLogger / GenericDTO / GenericRecorder.
* Common contains primitives only.
* No App\ or host-project dependencies.
* Repositories only persist/query and do not apply policy.

Phase 4 — Validation Gate

Status: Completed.

Required commands:

composer validate
composer install
find src -name "*.php" -exec php -l {} \;
vendor/bin/phpstan analyse -c phpstan.neon

All must pass.

Phase 5 — Final Release Audit

Status: Completed.

After fixes, create:

docs/audits/FINAL_RELEASE_AUDIT.md

Required verdict:

* PASS

Release is blocked until final audit passes with no blockers.
