# Documentation Inventory

| Path | Category | Status | Purpose | Notes / Required Action |
|---|---|---|---|---|
| `./CHANGELOG.md` | Root / Package Docs | Active | Tracks changes between releases |  |
| `./EVENT_LOGGING_PACKAGE_REFERENCE.md` | Core | Active | Current Runtime truth; primitive cursor contracts | Post-v1 pagination wrappers are documented as superseded experiments. |
| `./README.md` | Root / Package Docs | Active — Current Runtime Overview | Main entry point and package overview | Contains current guardrail wording: explicitly not self-contained, no generic logger/DTO/recorder/table, no SQLite examples. No cleanup required unless wording becomes ambiguous. |
| `./TESTING_STRATEGY.md` | Root / Package Docs | Active | Package-wide testing strategy | Safe guardrail wording: mentions generic logger/recorder/repo as regression test targets. |
| `./docs/architecture/FACTORY_AND_PROVIDER_DESIGN.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Reviewed: framework mentions are safe guardrails or explicit prohibitions; no current wording update needed. |
| `./docs/architecture/INTEGRATION_SURFACE_DESIGN.md` | Architecture Docs | Active | Architectural rules and logging patterns | Clarified that package may own domain-scoped reporting and dashboard summary contracts; host owns presentation. |
| `./docs/architecture/PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md` | Architecture Docs | Active | Protected `v1.0.0` primitive design | Scope explicitly restricted to v1 primitive path; host owns presentation and cross-system analytics. |
| `./docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md` | Architecture Docs | Active | Approved post-v1 architecture |  |
| `./docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented) | Blueprint and implementation status for AuditTrail POC | Owner approval granted; runtime implementation documented. |
| `./docs/architecture/ADMIN_QUERY_BEHAVIOR_TRACE_REBUILD_BLUEPRINT.md` | Architecture Docs | Active (Owner Approved / Runtime Implemented) | BehaviorTrace rebuild blueprint | Runtime implementation is complete. The approved replacement is present, and the superseded post-v1 pagination artifacts were deleted after the test gate passed. |
| `./docs/architecture/ADMIN_QUERY_SECURITY_SIGNALS_REBUILD_BLUEPRINT.md` | Architecture Docs | Active | SecuritySignals Admin Query Rebuild Blueprint | Proposed Blueprint / Runtime Implementation Blocked |
| `./docs/architecture/ADMIN_QUERY_SECURITY_SIGNALS_POST_V1_RETIREMENT_DECISION.md` | Architecture Docs | Active (Owner Decision) | Defines the mandatory retirement boundary for the SecuritySignals post-v1 wrapper | The seven superseded Runtime/test artifacts are outside `v1.0.0`, their wrapper/cursor contracts are not preserved, and they are deleted atomically in the Runtime rebuild. |
| `./docs/roadmap/ADMIN_QUERY_API_ROADMAP.md` | Roadmap Docs | Active | Current post-v1 execution roadmap |  |
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
| `./docs/audits/FINAL_TESTING_HARDENING_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, SQLite support, framework bindings (Slim, PHP-DI, etc), host app namespaces (App, Athar, EP4N) |
| `./docs/audits/FULL_ARCHITECTURE_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) |  |
| `./docs/audits/PHASE_0_DOCS_CLEANUP_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions host app namespaces (App, Athar, EP4N) |
| `./docs/audits/PHASE_2_FACTORY_PROVIDER_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, RuntimeException as storage exception |
| `./docs/audits/PHASE_3_CODE_CONSISTENCY_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions RuntimeException as storage exception |
| `./docs/audits/PHASE_3_PRIMITIVE_READ_SUPPORT_GAP_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions RuntimeException as storage exception |
| `./docs/audits/PHASE_3_PRIMITIVE_READ_SUPPORT_IMPLEMENTATION_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions RuntimeException as storage exception, framework bindings (Slim, PHP-DI, etc) |
| `./docs/audits/PHASE_5_VALIDATION_GATE.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, framework bindings (Slim, PHP-DI, etc) |
| `./docs/audits/PHASE_J_MAATIFY_CORE_CONTRACTS_ALIGNMENT_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions generic logger/recorder/repo, RuntimeException as storage exception, Common ClockInterface, framework bindings (Slim, PHP-DI, etc) |
| `./docs/audits/POST_PHASE_J_RELEASE_READINESS_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions SQLite support, RuntimeException as storage exception, Common ClockInterface, framework bindings (Slim, PHP-DI, etc) |
| `./docs/audits/STANDALONE_WORDING_CLARIFICATION_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions zero-dependency standalone, self-contained, dependency-free |
| `./docs/audits/WHOLE_LIBRARY_GAP_AUDIT.md` | Historical Audit Docs | Historical | Past audit record (not active) | Historical wording: mentions SQLite support, RuntimeException as storage exception, Common ClockInterface, framework bindings (Slim, PHP-DI, etc), host app namespaces (App, Athar, EP4N) |
| `./docs/examples/EXAMPLES_COVERAGE_PLAN.md` | Examples Docs | Active | Code example coverage and plans | Safe guardrail wording: explicitly states SQLite must not be presented as compatible. |
| `./docs/integration/ADMIN_READ_USAGE.md` | Public Integration Docs | Active | Instructions for integrating primitive reads and AuditTrail Admin Query | Documents host construction, filters, pagination response, sort behavior, and exception boundaries. |
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
| `./src/AuthoritativeAudit/README.md` | Domain Docs | Active | Domain overview |  |
| `./docs/archive/domain-docs/BehaviorTrace/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/BehaviorTrace/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist |  |
| `./src/BehaviorTrace/README.md` | Domain Docs | Active | Domain overview | Read scope boundary clarified. |
| `./docs/archive/domain-docs/BehaviorTrace/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |
| `./src/DeliveryOperations/README.md` | Domain Docs | Active | Domain overview |  |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist | Needs manual review: mentions host app namespaces (App, Athar, EP4N) |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/OPEN_QUESTIONS.md` | Domain Docs | Archived | Domain-specific internal documentation (Resolved) |  |
| `./src/DiagnosticsTelemetry/README.md` | Domain Docs | Active | Domain overview | Read scope boundary clarified. |
| `./docs/archive/domain-docs/DiagnosticsTelemetry/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |
| `./docs/archive/domain-docs/SecuritySignals/CANONICAL_ARCHITECTURE.md` | Domain Docs | Archived | Domain canonical architecture |  |
| `./docs/archive/domain-docs/SecuritySignals/CHECKLIST.md` | Domain Docs | Archived | Domain specific checklist |  |
| `./src/SecuritySignals/README.md` | Domain Docs | Active | Domain overview |  |
| `./docs/archive/domain-docs/SecuritySignals/TESTING_STRATEGY.md` | Domain Docs | Archived | Domain testing strategy |  |