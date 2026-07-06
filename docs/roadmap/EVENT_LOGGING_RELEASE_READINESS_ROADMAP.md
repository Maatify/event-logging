Event Logging Release Readiness Roadmap

Current Status

The package is not ready for release.

Current blocker:

* Database table names do not follow the Maatify module naming convention.
* Required table prefix: maa_event_logging_*

Phase 1 — Fix Database Naming

Rename all schema tables:

Current	Required
authoritative_audit_outbox	maa_event_logging_authoritative_audit_outbox
authoritative_audit_log	maa_event_logging_authoritative_audit_log
audit_trail	maa_event_logging_audit_trail
security_signals	maa_event_logging_security_signals
operational_activity	maa_event_logging_behavior_trace
diagnostics_telemetry	maa_event_logging_diagnostics_telemetry
delivery_operations	maa_event_logging_delivery_operations

Also update:

* SQL schema files
* MySQL repositories
* index names if needed
* schema comments
* schema/README.md
* package docs/examples

Phase 2 — Documentation Cleanup

Review and fix:

* README.md
* PUBLIC_API.md
* EVENT_LOGGING_MODULE_REFERENCE.md
* TESTING_STRATEGY.md
* CHANGELOG.md
* docs/audits/*
* schema/README.md
* domain README files if present

Rules:

* No incorrect dependency claims.
* No Athar Admin extraction history.
* No outdated table names.
* CI wording must match actual workflow behavior.
* Public docs must be suitable for release.

Phase 3 — Code Consistency Review

Verify:

* Commands are public input contracts.
* DTOs are read/output or internal recorder-to-writer transfer objects.
* Non-authoritative recorders are fail-open.
* AuthoritativeAudit remains fail-closed.
* No GenericLogger / GenericDTO / GenericRecorder.
* Common contains primitives only.
* No App\, Slim, or host-project dependencies.
* Repositories only persist/query and do not apply policy.

Phase 4 — Validation Gate

Required commands:

composer validate
composer install
find src -name "*.php" -exec php -l {} \;
vendor/bin/phpstan analyse -c phpstan.neon

All must pass.

Phase 5 — Final Release Audit

After fixes, create:

docs/audits/FINAL_RELEASE_AUDIT.md

Required verdict:

* PASS

Release is blocked until final audit passes with no blockers.