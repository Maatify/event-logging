# Domain Docs Archive Plan

## 1. Summary

* **Total `src/*/*.md` files reviewed:** 23
* **Keep Active:** 6
* **Archived:** 12
* **Merge-First Completed / Public API kept in src:** 4
* **Needs Architect Decision:** 1
* **Blocker before full archive:** Yes. The `OPEN_QUESTIONS.md` file requires architectural resolution before it can be archived or merged.

## 2. Per-file table

| Path | Current Inventory Status | Recommended Action | Reason | Merge Target | Risk Level |
|---|---|---|---|---|---|
| `docs/archive/domain-docs/AuditTrail/CANONICAL_ARCHITECTURE.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `docs/archive/domain-docs/AuditTrail/CHECKLIST.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `src/AuditTrail/PUBLIC_API.md` | Candidate for Dedicated Archive | Merge completed; keep in src for now | Merged into root PUBLIC_API.md / EVENT_LOGGING_MODULE_REFERENCE.md; kept for later dedicated archive decision | Completed | Medium |
| `src/AuditTrail/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `docs/archive/domain-docs/AuditTrail/TESTING_STRATEGY.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `src/AuthoritativeAudit/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `docs/archive/domain-docs/BehaviorTrace/CANONICAL_ARCHITECTURE.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `docs/archive/domain-docs/BehaviorTrace/CHECKLIST.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `src/BehaviorTrace/PUBLIC_API.md` | Candidate for Dedicated Archive | Merge completed; keep in src for now | Merged into root PUBLIC_API.md / EVENT_LOGGING_MODULE_REFERENCE.md; kept for later dedicated archive decision | Completed | Medium |
| `src/BehaviorTrace/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `docs/archive/domain-docs/BehaviorTrace/TESTING_STRATEGY.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `src/DeliveryOperations/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `docs/archive/domain-docs/DiagnosticsTelemetry/CANONICAL_ARCHITECTURE.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `docs/archive/domain-docs/DiagnosticsTelemetry/CHECKLIST.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `docs/archive/domain-docs/DiagnosticsTelemetry/OPEN_QUESTIONS.md` | Archived | Archived | Resolved and archived. | N/A | Low |
| `src/DiagnosticsTelemetry/PUBLIC_API.md` | Candidate for Dedicated Archive | Merge completed; keep in src for now | Merged into root PUBLIC_API.md / EVENT_LOGGING_MODULE_REFERENCE.md; kept for later dedicated archive decision | Completed | Medium |
| `src/DiagnosticsTelemetry/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `docs/archive/domain-docs/DiagnosticsTelemetry/TESTING_STRATEGY.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `docs/archive/domain-docs/SecuritySignals/CANONICAL_ARCHITECTURE.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `docs/archive/domain-docs/SecuritySignals/CHECKLIST.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |
| `src/SecuritySignals/PUBLIC_API.md` | Candidate for Dedicated Archive | Merge completed; keep in src for now | Merged into root PUBLIC_API.md / EVENT_LOGGING_MODULE_REFERENCE.md; kept for later dedicated archive decision | Completed | Medium |
| `src/SecuritySignals/README.md` | Active | Keep Active | Domain overview and entry point. | N/A | Low |
| `docs/archive/domain-docs/SecuritySignals/TESTING_STRATEGY.md` | Archived | Archived | Safe Archive Batch completed | N/A | Low |

## 3. Safe Archive Batch

The following files have been successfully moved to `docs/archive/domain-docs/`:

* `docs/archive/domain-docs/AuditTrail/CANONICAL_ARCHITECTURE.md`
* `docs/archive/domain-docs/AuditTrail/CHECKLIST.md`
* `docs/archive/domain-docs/AuditTrail/TESTING_STRATEGY.md`
* `docs/archive/domain-docs/BehaviorTrace/CANONICAL_ARCHITECTURE.md`
* `docs/archive/domain-docs/BehaviorTrace/CHECKLIST.md`
* `docs/archive/domain-docs/BehaviorTrace/TESTING_STRATEGY.md`
* `docs/archive/domain-docs/DiagnosticsTelemetry/CANONICAL_ARCHITECTURE.md`
* `docs/archive/domain-docs/DiagnosticsTelemetry/CHECKLIST.md`
* `docs/archive/domain-docs/DiagnosticsTelemetry/TESTING_STRATEGY.md`
* `docs/archive/domain-docs/SecuritySignals/CANONICAL_ARCHITECTURE.md`
* `docs/archive/domain-docs/SecuritySignals/CHECKLIST.md`
* `docs/archive/domain-docs/SecuritySignals/TESTING_STRATEGY.md`

## 4. Merge Before Archive — Completed

The Merge-First Batch is now completed. The following files remain in `src/` for now and may be archived in a later dedicated batch only if desired:

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

No files currently require an architect decision. `OPEN_QUESTIONS.md` has been successfully resolved and archived.

## 7. Recommended Next Step

**Archive Complete:** The Safe Archive Batch has been executed. The `PUBLIC_API.md` files' Merge-First Batch is complete. `OPEN_QUESTIONS.md` is resolved and archived. There are no remaining domain docs archive blockers.
