# Documentation Inventory

| Path | Category | Status | Purpose | Notes / Required Action |
|---|---|---|---|---|
| `./CHANGELOG.md` | Root / Package Docs | Active | Tracks changes between releases |  |
| `./EVENT_LOGGING_MODULE_REFERENCE.md` | Root / Package Docs | Active | Package level documentation | Safe guardrail wording: mentions generic logger/recorder/repo within prohibited architecture context. |
| `./PUBLIC_API.md` | Root / Package Docs | Active | Package level documentation |  |
| `./README.md` | Root / Package Docs | Active | Main entry point and package overview | Contains current guardrail wording: explicitly not self-contained, no generic logger/DTO/recorder/table, no SQLite examples. No cleanup required unless wording becomes ambiguous. |
| `./TESTING_STRATEGY.md` | Root / Package Docs | Active | Package-wide testing strategy | Safe guardrail wording: mentions generic logger/recorder/repo as regression test targets. |
| `./docs/architecture/FACTORY_AND_PROVIDER_DESIGN.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Needs manual review: mentions framework bindings (Slim, PHP-DI, etc) |
| `./docs/architecture/INTEGRATION_SURFACE_DESIGN.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Needs manual review: mentions generic logger/recorder/repo, framework bindings (Slim, PHP-DI, etc) |
| `./docs/architecture/PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Needs manual review: mentions framework bindings (Slim, PHP-DI, etc) |
| `./docs/architecture/logging/CANONICAL_LOGGER_DESIGN_STANDARD.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/GLOBAL_LOGGING_RULES.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/LOGGING_MODULE_BLUEPRINT.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Needs manual review: mentions generic logger/recorder/repo |
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
| `./docs/integration/ADMIN_READ_USAGE.md` | Public Integration Docs | Active | Instructions for integrating the package |  |
| `./docs/integration/FACTORY_USAGE.md` | Public Integration Docs | Active | Instructions for integrating the package |  |
| `./docs/integration/INSTALLATION.md` | Public Integration Docs | Active | Instructions for integrating the package | Needs manual review: mentions framework bindings (Slim, PHP-DI, etc) |
| `./docs/integration/MANUAL_WIRING.md` | Public Integration Docs | Active | Instructions for integrating the package | Needs manual review: mentions framework bindings (Slim, PHP-DI, etc) |
| `./docs/reference/logging/ASCII_FLOW_LEGENDS.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/reference/logging/LOGGING_ASCII_OVERVIEW.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./docs/reference/logging/LOGGING_LIBRARY_STRUCTURE_CANONICAL.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Needs manual review: mentions generic logger/recorder/repo |
| `./docs/roadmap/EVENT_LOGGING_INTEGRATION_READINESS_ROADMAP.md` | Roadmap Docs | Active | Future plans and readiness tracks | Reviewed: previous exception-policy wording was corrected; remaining generic/framework mentions are historical roadmap context or guardrails. |
| `./docs/roadmap/EVENT_LOGGING_RELEASE_READINESS_ROADMAP.md` | Roadmap Docs | Active | Future plans and readiness tracks |  |
| `./docs/roadmap/TESTING_AND_EXAMPLES_HARDENING_ROADMAP.md` | Roadmap Docs | Active | Future plans and readiness tracks | Needs manual review: mentions generic logger/recorder/repo, framework bindings (Slim, PHP-DI, etc) |
| `./docs/standards/PACKAGE_BUILDING_STANDARD.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Resolved (Updated to Package Standard): `RuntimeException` is completely forbidden and replaced with `SystemMaatifyException`; no longer recommends framework bindings. |
| `./docs/testing/TEST_COVERAGE_MATRIX.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns |  |
| `./schema/README.md` | Standards / Architecture Docs | Active | Architectural rules and logging patterns | Needs manual review: mentions generic logger/recorder/repo |
| `./src/AuditTrail/CANONICAL_ARCHITECTURE.md` | Domain Docs | Candidate for Archive | Domain canonical architecture |  |
| `./src/AuditTrail/CHECKLIST.md` | Domain Docs | Candidate for Archive | Domain specific checklist |  |
| `./src/AuditTrail/PUBLIC_API.md` | Domain Docs | Candidate for Archive | Domain public API |  |
| `./src/AuditTrail/README.md` | Domain Docs | Active | Domain overview |  |
| `./src/AuditTrail/TESTING_STRATEGY.md` | Domain Docs | Candidate for Archive | Domain testing strategy |  |
| `./src/AuthoritativeAudit/README.md` | Domain Docs | Active | Domain overview |  |
| `./src/BehaviorTrace/CANONICAL_ARCHITECTURE.md` | Domain Docs | Candidate for Archive | Domain canonical architecture |  |
| `./src/BehaviorTrace/CHECKLIST.md` | Domain Docs | Candidate for Archive | Domain specific checklist |  |
| `./src/BehaviorTrace/PUBLIC_API.md` | Domain Docs | Candidate for Archive | Domain public API |  |
| `./src/BehaviorTrace/README.md` | Domain Docs | Active | Domain overview |  |
| `./src/BehaviorTrace/TESTING_STRATEGY.md` | Domain Docs | Candidate for Archive | Domain testing strategy |  |
| `./src/DeliveryOperations/README.md` | Domain Docs | Active | Domain overview |  |
| `./src/DiagnosticsTelemetry/CANONICAL_ARCHITECTURE.md` | Domain Docs | Candidate for Archive | Domain canonical architecture |  |
| `./src/DiagnosticsTelemetry/CHECKLIST.md` | Domain Docs | Candidate for Archive | Domain specific checklist | Needs manual review: mentions host app namespaces (App, Athar, EP4N) |
| `./src/DiagnosticsTelemetry/OPEN_QUESTIONS.md` | Domain Docs | Candidate for Archive | Domain-specific internal documentation |  |
| `./src/DiagnosticsTelemetry/PUBLIC_API.md` | Domain Docs | Candidate for Archive | Domain public API |  |
| `./src/DiagnosticsTelemetry/README.md` | Domain Docs | Active | Domain overview |  |
| `./src/DiagnosticsTelemetry/TESTING_STRATEGY.md` | Domain Docs | Candidate for Archive | Domain testing strategy |  |
| `./src/SecuritySignals/CANONICAL_ARCHITECTURE.md` | Domain Docs | Candidate for Archive | Domain canonical architecture |  |
| `./src/SecuritySignals/CHECKLIST.md` | Domain Docs | Candidate for Archive | Domain specific checklist |  |
| `./src/SecuritySignals/PUBLIC_API.md` | Domain Docs | Candidate for Archive | Domain public API |  |
| `./src/SecuritySignals/README.md` | Domain Docs | Active | Domain overview |  |
| `./src/SecuritySignals/TESTING_STRATEGY.md` | Domain Docs | Candidate for Archive | Domain testing strategy |  |

## Recommended Cleanup Plan

### 1. Manual Review of Flagged Files
- The first step is to perform a manual review of all files marked with `Needs manual review` to separate false positives (safe guardrail wording) from actual contradictions (unsafe wording).
- Examples of correctly verified guardrail files include `README.md`, `EVENT_LOGGING_MODULE_REFERENCE.md`, `TESTING_STRATEGY.md`, and `docs/examples/EXAMPLES_COVERAGE_PLAN.md`.

### 2. Files for Potential Consolidation / Merge
- Some `Standards / Architecture Docs` such as multiple logging rule files in `docs/architecture/logging/` could be merged to reduce fragmentation.
- Public Integration Docs (`docs/integration/`) might be consolidated into a single comprehensive guide if appropriate.

### 3. Files to Transition to Historical/Archive
- All domain-level supplementary docs (e.g., `CHECKLIST.md`, `CANONICAL_ARCHITECTURE.md`, `PUBLIC_API.md`, `TESTING_STRATEGY.md`) currently marked as `Candidate for Archive`.
- Several older audits in `docs/audits/` are already historical but should remain for reference.

### 4. Files Requiring Wording Updates (After Manual Review)
- For files confirmed to have unsafe wording:
  - Replace "standalone" / "zero-dependency" / "self-contained" / "dependency-free" with "framework-agnostic standalone Composer package" (explicitly noting dependencies like `psr/log`, `ext-json`, `ext-pdo`).
  - Remove references to `SQLite` and `RuntimeException` for storage exceptions.
  - Remove references to generic loggers or repositories; enforce strict domain-isolated contracts.
  - Ensure no framework bindings (Slim, PHP-DI, Laravel) are implied as part of the package itself.
  - Remove references to host app namespaces (`App\`, `Athar`, `EP4N`).

### 5. Files for Potential Removal (No action to be taken yet)
- Older audit files that have been superseded by final architecture audits.
- Redundant translation files (`unified-logging-system.ar.md`, `unified-logging-system.en.md`) if a single source of truth is preferred.
