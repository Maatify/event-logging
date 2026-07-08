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
| `./docs/roadmap/EVENT_LOGGING_INTEGRATION_READINESS_ROADMAP.md` | Resolved unsafe wording | Instructs to "Verify named constructors and RuntimeException inheritance". This contradicts the project requirement that exceptions must extend `SystemMaatifyException` and return a specific enum. | Keep as-is | Resolved by roadmap exception-policy wording update. |
| `./docs/roadmap/TESTING_AND_EXAMPLES_HARDENING_ROADMAP.md` | Safe guardrail wording | Lists items like "Ensure no GenericLogger" and "Ensure no framework bindings" as architecture regression test goals. | Keep as-is | None needed. |
| `./docs/standards/PACKAGE_BUILDING_STANDARD.md` | Resolved unsafe wording | Recommended `\RuntimeException` for exceptions and used `php-di/php-di` `ContainerBuilder` for DI bindings. This conflicted with the standalone library rules. | Keep as package standard | Resolved. The document was rewritten into a `PACKAGE_BUILDING_STANDARD` and completely removes `RuntimeException`, enforces `SystemMaatifyException`, and forbids framework DI bindings. |
| `./schema/README.md` | Safe guardrail wording | States the domain isolation "avoids implying a shared generic log table". | Keep as-is | None needed. |
| `./src/DiagnosticsTelemetry/CHECKLIST.md` | Safe guardrail wording | Checklist verifies "No dependencies on App\Models or App\Services". | Convert to historical/archive | Wording is safe, but this is a domain checklist meant for the archive. |

## Prioritized Cleanup Recommendations

### Fix Now Before v1.0.0-rc.1
No remaining Fix Now documentation wording blockers identified by this report.

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
- `./docs/standards/PACKAGE_BUILDING_STANDARD.md` (Now updated to reflect safe package standards)

### Archive / Consolidation Candidates
- **`./src/DiagnosticsTelemetry/CHECKLIST.md`**: The wording is correct, but the document itself is a candidate for the historical archive alongside other domain checklists.

### Architect Decision Needed
No remaining architect decisions needed from this report.
