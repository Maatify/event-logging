# Documentation Inventory

This document tracks the current state, location, and purpose of all Markdown documentation in the `maatify/event-logging` package. It prevents duplicate instructions and helps identify obsolete files.

| File Path | Category | Status | Primary Purpose | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `../../README.md` | Public Integration Docs | Active | General overview and host installation guide | Strict compliance with `LIBRARY_PRESENTATION_STANDARD.md` required. |
| `../../EVENT_LOGGING_PACKAGE_REFERENCE.md` | Standards / Architecture Docs | Active | The canonical library contract | Single source of truth for the stable public interface. |
| `../../SECURITY.md` | Public Integration Docs | Active | Security rules | Safe guardrail wording. |
| `../../CHANGELOG.md` | Public Integration Docs | Active | Version release notes | Must adhere to Keep a Changelog standard exactly. |
| `./docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md` | Architecture Docs | Active | Unified architecture for Admin Query API work | Replaces scattered domain-specific architecture documents for the Admin Query API. |
| `./docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented) | Blueprint for AuditTrail Admin Query POC | Runtime implementation is complete and merged. Protected `v1.0.0` contracts are preserved. |
| `./docs/architecture/ADMIN_QUERY_AUTHORITATIVE_AUDIT_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented / Complete) | Blueprint for AuthoritativeAudit Admin Query API | Runtime implementation is complete and merged. Protected `v1.0.0` contracts are preserved, and superseded post-v1 pagination artifacts were deleted. |
| `./docs/architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented) | Blueprint for BehaviorTrace Admin Query Rebuild | Runtime implementation is complete and merged. Protected `v1.0.0` contracts are preserved, and superseded post-v1 pagination artifacts were deleted. |
| `./docs/architecture/ADMIN_QUERY_DELIVERY_OPERATIONS_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented / Complete) | Blueprint for DeliveryOperations Admin Query API | DeliveryOperations Admin Query Runtime is complete and merged. |
| `./docs/architecture/ADMIN_QUERY_DIAGNOSTICS_TELEMETRY_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented / Complete) | Blueprint for DiagnosticsTelemetry Admin Query API | DiagnosticsTelemetry Admin Query Runtime is complete and merged. The protected primitive find()/read() behavior is preserved, the native-PDO distinct-placeholder correction is applied, and Unit, Regression, and strict real-MySQL Integration coverage is present. |
| `./docs/architecture/ADMIN_QUERY_SECURITY_SIGNALS_REBUILD_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented) | SecuritySignals Admin Query Rebuild Blueprint | Runtime implementation is complete. The primitive cursor placeholder correction is applied, coverage is complete, and the superseded post-v1 pagination artifacts were deleted. |
| `./docs/architecture/ADMIN_QUERY_SECURITY_SIGNALS_POST_V1_RETIREMENT_DECISION.md` | Architecture Docs | Active (Owner Decision) | Defines the mandatory retirement boundary for the SecuritySignals post-v1 wrapper | The seven superseded Runtime/test artifacts are outside `v1.0.0`, their wrapper/cursor contracts are not preserved, and they are deleted atomically in the Runtime rebuild. |
| `./docs/roadmap/ADMIN_QUERY_API_ROADMAP.md` | Roadmap Docs | Active | Current post-v1 execution roadmap | Phase 4 complete; All domains implemented; reporting/dashboard remains blocked; no release or tag is authorized. |
| `./docs/audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md` | Historical Audit Docs | Historical | Historical Phase 1 Baseline |  |
| `./docs/architecture/logging/CANONICAL_LOGGER_DESIGN_STANDARD.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/GLOBAL_LOGGING_RULES.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/LOGGING_MODULE_BLUEPRINT.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Reviewed: generic/framework mentions are safe guardrails or explicit prohibitions; no current wording update needed. |
| `./docs/architecture/logging/LOG_STORAGE_AND_ARCHIVING.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/README.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/UNIFIED_LOGGING_DESIGN.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/unified-logging-system.ar.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/unified-logging-system.en.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/audits/ADMIN_READ_BINDING_REFERENCE_ALIGNMENT.md` | Historical Audit Docs | Historical | Past audit record (not active) |  |
| `./docs/audits/AUDIT_REPORT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, RuntimeException as storage exception |
| `./docs/audits/DOCUMENTATION_EXCELLENCE_REVIEW.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions SQLite support, Common ClockInterface |
| `./docs/audits/DOCUMENTATION_FINAL_EXCELLENCE_REVIEW.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions RuntimeException as storage exception |
| `./docs/audits/DOCUMENTATION_FINAL_VERIFICATION.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions SQLite support, RuntimeException as storage exception, Common ClockInterface, framework bindings (Slim, PHP-DI, etc) |
| `./docs/audits/DOCUMENTATION_GAP_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, SQLite support, RuntimeException as storage exception, Common ClockInterface, framework bindings (Slim, PHP-DI, etc), host app namespaces (App, Athar, EP4N) |
| `./docs/audits/DOCUMENTATION_INVENTORY.md` | Active Audit Docs | Active | Current Markdown documentation inventory and cleanup planning aid | Active working audit; should be refreshed when documentation structure changes. |
| `./docs/audits/FINAL_DOCUMENTATION_STATE_CLEANUP_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions SQLite support, RuntimeException as storage exception |
| `./docs/audits/FINAL_INTEGRATION_RELEASE_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, framework bindings (Slim, PHP-DI, etc) |
| `./docs/audits/FINAL_RELEASE_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, RuntimeException as storage exception, framework bindings (Slim, PHP-DI, etc), host app namespaces (App, Athar, EP4N) |
| `./docs/audits/PACKAGE_REFERENCE_COMPATIBILITY_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, SQLite support, RuntimeException as storage exception, Common ClockInterface, host app namespaces (App, Athar, EP4N) |
| `./docs/audits/RELEASE_READINESS_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions RuntimeException as storage exception, Common ClockInterface |
| `./docs/audits/TESTING_STRATEGY_COMPATIBILITY_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions SQLite support, RuntimeException as storage exception, Common ClockInterface |
| `./docs/audits/ADMIN_QUERY_DIAGNOSTICS_TELEMETRY_AUDIT.md` | Active Audit Docs | Active | Audit and baseline for DiagnosticsTelemetry Admin Query | Active evidence document. Not an architecture authority. Not an implemented Runtime contract. |
| `./docs/audits/ADMIN_QUERY_DELIVERY_OPERATIONS_AUDIT.md` | Active Audit Docs | Active | Audit and baseline for DeliveryOperations Admin Query | Active evidence document. Not an architecture authority. Not an implemented Runtime contract. |
| `./docs/audits/STANDALONE_WORDING_CLARIFICATION_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions zero-dependency standalone, self-contained, dependency-free |
| `./docs/audits/ADMIN_QUERY_AUTHORITATIVE_AUDIT_AUDIT.md` | Historical Audit Docs | Historical | Historical audit for AuthoritativeAudit remediation | Historical/resolved. Superseded by approved Blueprint. |
| `./docs/audits/WHOLE_LIBRARY_GAP_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions SQLite support, RuntimeException as storage exception, Common ClockInterface, framework bindings (Slim, PHP-DI, etc), host app namespaces (App, Athar, EP4N) |
| `./docs/examples/EXAMPLES_COVERAGE_PLAN.md` | Examples Docs | Active | Code example coverage and plans | Safe guardrail wording: explicitly states SQLite must not be presented as compatible. |
| `./docs/integration/ADMIN_READ_USAGE.md` | Public Integration Docs | Active | Instructions for integrating primitive reads and Admin Query APIs | Documents host construction, filters, pagination response, sort behavior, and exception boundaries for implemented Admin Query domains. |
| `./docs/integration/FACTORY_USAGE.md` | Public Integration Docs | Active | Instructions for integrating the package |  |
| `./docs/integration/INSTALLATION.md` | Public Integration Docs | Active | Instructions for integrating the package | Reviewed: framework mentions are safe guardrails or explicit prohibitions; no current wording update needed. |
| `./docs/integration/MANUAL_WIRING.md` | Public Integration Docs | Active | Instructions for integrating the package | Reviewed: framework mentions are safe guardrails or explicit prohibitions; no current wording update needed. |
| `./docs/reference/logging/ASCII_FLOW_LEGENDS.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/reference/logging/LOGGING_ASCII_OVERVIEW.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/reference/logging/LOGGING_LIBRARY_STRUCTURE_CANONICAL.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Reviewed: generic/framework mentions are safe guardrails or explicit prohibitions; no current wording update needed. |
| `./docs/roadmap/EVENT_LOGGING_INTEGRATION_READINESS_ROADMAP.md` | Roadmap Docs | Historical | Completed historical v1.0.0 roadmap | Added status banner identifying it as historical. |
| `./docs/roadmap/EVENT_LOGGING_RELEASE_READINESS_ROADMAP.md` | Roadmap Docs | Historical | Completed historical v1.0.0 roadmap | Added status banner identifying it as historical. |
| `./docs/roadmap/TESTING_AND_EXAMPLES_HARDENING_ROADMAP.md` | Roadmap Docs | Active | Future plans and readiness tracks | Reviewed: generic/framework mentions are safe guardrails or explicit prohibitions; no current wording update needed. |
| `./docs/standards/PACKAGE_BUILDING_STANDARD.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Resolved (Updated to Package Standard): `RuntimeException` is completely forbidden and replaced with `SystemMaatifyException`; no longer recommends framework bindings. |
| `./docs/testing/TEST_COVERAGE_MATRIX.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Pagination row explicitly named "Primitive v1.0 Cursor Pagination (DESC)". |
| `./schema/README.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Reviewed: generic/framework mentions are safe guardrails or explicit prohibitions; no current wording update needed. |
| `./docs/archive/domain-docs/AuditTrail/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/AuditTrail/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist |  |
| `./src/AuditTrail/README.md` | Domain Docs | Active | Domain overview | Documents primitive query and separate Admin Query pagination API. |
| `./docs/archive/domain-docs/AuditTrail/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |
| `./docs/archive/domain-docs/AuthoritativeAudit/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/AuthoritativeAudit/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist | Needs manual review: mentions generic logger/recorder/repo, RuntimeException as storage exception, framework bindings (Slim, PHP-DI, etc), host app namespaces (App, Athar, EP4N) |
| `./src/AuthoritativeAudit/README.md` | Domain Docs | Active | Domain overview | Read scope boundary clarified. |
| `./docs/archive/domain-docs/AuthoritativeAudit/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |
| `./docs/archive/domain-docs/BehaviorTrace/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/BehaviorTrace/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist |  |
| `./src/BehaviorTrace/README.md` | Domain Docs | Active | Domain overview | Documents primitive query and separate Admin Query pagination API. |
| `./docs/archive/domain-docs/BehaviorTrace/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |
| `./docs/archive/domain-docs/DeliveryOperations/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/DeliveryOperations/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist |  |
| `./src/DeliveryOperations/README.md` | Domain Docs | Active | Domain overview |  |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist | Needs manual review: mentions host app namespaces (App, Athar, EP4N) |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/OPEN_QUESTIONS.md` | Domain Docs | Archived | Domain-specific internal documentation (Resolved) |  |
| `./src/DiagnosticsTelemetry/README.md` | Domain Docs | Active | Domain overview | Read scope boundary clarified. |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |
| `./docs/archive/domain-docs/SecuritySignals/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/SecuritySignals/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist |  |
| `./src/SecuritySignals/README.md` | Domain Docs | Active | Domain overview | Documents recording and separate Admin Query pagination API. |
| `./docs/archive/domain-docs/SecuritySignals/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |
