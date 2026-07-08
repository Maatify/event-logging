# Domain Docs Archive Plan

## 1. Summary

* **Total `src/*/*.md` files reviewed:** 23
* **Keep Active:** 6
* **Archive Candidate:** 12
* **Merge Into Package Docs First:** 4
* **Needs Architect Decision:** 1
* **Blocker before full archive:** Yes. The `PUBLIC_API.md` files contain public entry point definitions that must be merged into global integration documentation first, and `OPEN_QUESTIONS.md` requires architectural resolution before it can be archived or merged.

## 2. Per-file table

| Path | Current Inventory Status | Recommended Action | Reason | Merge Target | Risk Level |
|---|---|---|---|---|---|
| `src/AuditTrail/CANONICAL_ARCHITECTURE.md` | Candidate for Archive | Archive Candidate | Redundant with global architecture docs. | N/A | Low |
| `src/AuditTrail/CHECKLIST.md` | Candidate for Archive | Archive Candidate | Historical checklist, no longer needed. | N/A | Low |
| `src/AuditTrail/PUBLIC_API.md` | Candidate for Archive | Merge Into Package Docs First | Contains specific contract and API definitions needed for usage. | `PUBLIC_API.md` / `EVENT_LOGGING_MODULE_REFERENCE.md` | Medium |
| `src/AuditTrail/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `src/AuditTrail/TESTING_STRATEGY.md` | Candidate for Archive | Archive Candidate | Outdated, covered by global testing matrix. | N/A | Low |
| `src/AuthoritativeAudit/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `src/BehaviorTrace/CANONICAL_ARCHITECTURE.md` | Candidate for Archive | Archive Candidate | Redundant with global architecture docs. | N/A | Low |
| `src/BehaviorTrace/CHECKLIST.md` | Candidate for Archive | Archive Candidate | Historical checklist, no longer needed. | N/A | Low |
| `src/BehaviorTrace/PUBLIC_API.md` | Candidate for Archive | Merge Into Package Docs First | Contains specific contract and API definitions needed for usage. | `PUBLIC_API.md` / `EVENT_LOGGING_MODULE_REFERENCE.md` | Medium |
| `src/BehaviorTrace/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `src/BehaviorTrace/TESTING_STRATEGY.md` | Candidate for Archive | Archive Candidate | Outdated, covered by global testing matrix. | N/A | Low |
| `src/DeliveryOperations/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `src/DiagnosticsTelemetry/CANONICAL_ARCHITECTURE.md` | Candidate for Archive | Archive Candidate | Redundant with global architecture docs. | N/A | Low |
| `src/DiagnosticsTelemetry/CHECKLIST.md` | Candidate for Archive | Archive Candidate | Historical checklist, no longer needed. | N/A | Low |
| `src/DiagnosticsTelemetry/OPEN_QUESTIONS.md` | Candidate for Archive | Needs Architect Decision | Unresolved questions regarding PDO connections and UUID dependency. | N/A | High |
| `src/DiagnosticsTelemetry/PUBLIC_API.md` | Candidate for Archive | Merge Into Package Docs First | Contains specific contract and API definitions needed for usage. | `PUBLIC_API.md` / `EVENT_LOGGING_MODULE_REFERENCE.md` | Medium |
| `src/DiagnosticsTelemetry/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `src/DiagnosticsTelemetry/TESTING_STRATEGY.md` | Candidate for Archive | Archive Candidate | Outdated, covered by global testing matrix. | N/A | Low |
| `src/SecuritySignals/CANONICAL_ARCHITECTURE.md` | Candidate for Archive | Archive Candidate | Redundant with global architecture docs. | N/A | Low |
| `src/SecuritySignals/CHECKLIST.md` | Candidate for Archive | Archive Candidate | Historical checklist, no longer needed. | N/A | Low |
| `src/SecuritySignals/PUBLIC_API.md` | Candidate for Archive | Merge Into Package Docs First | Contains specific contract and API definitions needed for usage. | `PUBLIC_API.md` / `EVENT_LOGGING_MODULE_REFERENCE.md` | Medium |
| `src/SecuritySignals/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `src/SecuritySignals/TESTING_STRATEGY.md` | Candidate for Archive | Archive Candidate | Outdated, covered by global testing matrix. | N/A | Low |

## 3. Safe Archive Batch

The following files can be moved directly to `docs/archive/domain-docs/` in a subsequent task:

* `src/AuditTrail/CANONICAL_ARCHITECTURE.md`
* `src/AuditTrail/CHECKLIST.md`
* `src/AuditTrail/TESTING_STRATEGY.md`
* `src/BehaviorTrace/CANONICAL_ARCHITECTURE.md`
* `src/BehaviorTrace/CHECKLIST.md`
* `src/BehaviorTrace/TESTING_STRATEGY.md`
* `src/DiagnosticsTelemetry/CANONICAL_ARCHITECTURE.md`
* `src/DiagnosticsTelemetry/CHECKLIST.md`
* `src/DiagnosticsTelemetry/TESTING_STRATEGY.md`
* `src/SecuritySignals/CANONICAL_ARCHITECTURE.md`
* `src/SecuritySignals/CHECKLIST.md`
* `src/SecuritySignals/TESTING_STRATEGY.md`

## 4. Merge Before Archive

The following files should have their core API points/contracts/DTOs merged into `PUBLIC_API.md` and their design rules/usage references merged into `EVENT_LOGGING_MODULE_REFERENCE.md` before archiving:

* `src/AuditTrail/PUBLIC_API.md`
* `src/BehaviorTrace/PUBLIC_API.md`
* `src/DiagnosticsTelemetry/PUBLIC_API.md`
* `src/SecuritySignals/PUBLIC_API.md`

## 5. Keep Active

These domain overview files serve as the primary introduction to each namespace and must remain active in their current location:

* `src/AuditTrail/README.md`
* `src/AuthoritativeAudit/README.md`
* `src/BehaviorTrace/README.md`
* `src/DeliveryOperations/README.md`
* `src/DiagnosticsTelemetry/README.md`
* `src/SecuritySignals/README.md`

## 6. Architect Decision Needed

The following file contains open technical questions that require a decision from an architect before it can be closed or archived:

* `src/DiagnosticsTelemetry/OPEN_QUESTIONS.md` (Contains unresolved questions regarding the injected PDO connection and UUID dependency approach).

## 7. Recommended Next Step

**Merge-First Batch:** Proceed with extracting the specific public API and contract definitions from the domain `PUBLIC_API.md` files and merging them into `PUBLIC_API.md` and/or `EVENT_LOGGING_MODULE_REFERENCE.md`.
