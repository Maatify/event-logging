# Documentation Manual Review Report

This report evaluates files flagged with `Needs manual review` in the `DOCUMENTATION_INVENTORY.md` to distinguish between unsafe contradictions and safe guardrail wording.

## Review Findings

| Path | Finding Type | Context | Verdict | Recommended Action |
|---|---|---|---|---|
| `./docs/architecture/FACTORY_AND_PROVIDER_DESIGN.md` | Safe guardrail wording | Explicitly states "No Slim/PHP-DI bindings" to enforce framework-agnostic design. | Keep as-is | None needed. Wording correctly forbids framework bindings. |
| `./docs/architecture/INTEGRATION_SURFACE_DESIGN.md` | Safe guardrail wording | Mentions "NOT expose a generic EventLoggingManager", "prevents the introduction of GenericLogger", and lists forbidden frameworks. | Keep as-is | None needed. Mentions act as explicit prohibitions. |
| `./docs/architecture/PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md` | Safe guardrail wording | Specifies "NO GenericReader" and "NO Framework-specific bindings (e.g., Slim, Laravel)". | Keep as-is | None needed. Strict architectural constraints are clear. |
| `./docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md` | Safe guardrail wording | States that `BehaviorTrace` MUST NEVER be used as a fallback or "generic logger". | Keep as-is | None needed. |
| `./docs/integration/INSTALLATION.md` | Safe guardrail wording | States "does not assume usage of Slim, Laravel, Symfony, PHP-DI" to emphasize isolation. | Keep as-is | None needed. |
| `./docs/integration/MANUAL_WIRING.md` | Safe guardrail wording | Explains there are "no generic loggers" and "No Internal Framework Bindings" for DI containers. | Keep as-is | None needed. |
| `./docs/reference/logging/LOGGING_LIBRARY_STRUCTURE_CANONICAL.md` | Safe guardrail wording | Prohibits a "generic log event" DTO. | Keep as-is | None needed. |
| `./docs/roadmap/EVENT_LOGGING_INTEGRATION_READINESS_ROADMAP.md` | Unsafe current wording | Instructs to "Verify named constructors and RuntimeException inheritance". This contradicts the project requirement that exceptions must extend `SystemMaatifyException` and return a specific enum. | Update wording | Update wording to require extending `SystemMaatifyException` instead of `RuntimeException`. |
| `./docs/roadmap/TESTING_AND_EXAMPLES_HARDENING_ROADMAP.md` | Safe guardrail wording | Lists items like "Ensure no GenericLogger" and "Ensure no framework bindings" as architecture regression test goals. | Keep as-is | None needed. |
| `./docs/standards/MODULE_BUILDING_STANDARD.md` | Host-app coupling risk | Recommends `\RuntimeException` for exceptions and uses `php-di/php-di` `ContainerBuilder` for DI bindings. This conflicts with the standalone library rules (using `SystemMaatifyException` and no PHP-DI bindings). | Needs architect decision | Clarify if this standard applies only to host-app modules or needs specific standalone library exceptions added to the standard. |
| `./schema/README.md` | Safe guardrail wording | States the domain isolation "avoids implying a shared generic log table". | Keep as-is | None needed. |
| `./src/DiagnosticsTelemetry/CHECKLIST.md` | Safe guardrail wording | Checklist verifies "No dependencies on App\Models or App\Services". | Convert to historical/archive | Wording is safe, but this is a domain checklist meant for the archive. |

## Prioritized Cleanup Recommendations

### Fix Now Before v1.0.0-rc.1
- **`./docs/roadmap/EVENT_LOGGING_INTEGRATION_READINESS_ROADMAP.md`**: Contains a direct contradiction with the current exception architecture (`RuntimeException` vs `SystemMaatifyException`). Needs an immediate update to ensure the roadmap aligns with the required implementation.

### Keep As Guardrails
The following files contain correctly phrased prohibitions (e.g., "no generic logger", "no framework bindings") and should remain exactly as they are:
- `./docs/architecture/FACTORY_AND_PROVIDER_DESIGN.md`
- `./docs/architecture/INTEGRATION_SURFACE_DESIGN.md`
- `./docs/architecture/PRIMITIVE_READ_QUERY_SUPPORT_DESIGN.md`
- `./docs/architecture/logging/LOG_DOMAINS_OVERVIEW.md`
- `./docs/integration/INSTALLATION.md`
- `./docs/integration/MANUAL_WIRING.md`
- `./docs/reference/logging/LOGGING_LIBRARY_STRUCTURE_CANONICAL.md`
- `./docs/roadmap/TESTING_AND_EXAMPLES_HARDENING_ROADMAP.md`
- `./schema/README.md`

### Archive / Consolidation Candidates
- **`./src/DiagnosticsTelemetry/CHECKLIST.md`**: The wording is correct, but the document itself is a candidate for the historical archive alongside other domain checklists.

### Architect Decision Needed
- **`./docs/standards/MODULE_BUILDING_STANDARD.md`**: This document enforces `RuntimeException` and provides `php-di` DI container examples. An architect must decide whether to update the standard to accommodate the standalone `maatify/event-logging` rules (which require `SystemMaatifyException` and no DI) or to explicitly state that the library is an exception to these specific standard rules.
