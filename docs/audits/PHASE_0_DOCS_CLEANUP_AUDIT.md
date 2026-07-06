# Phase 0 Docs Cleanup Audit

## Summary of Changes
Cleaned all the files under `docs/architecture/logging/` to make the documentation valid for the standalone package `maatify/event-logging`.

### File Classification Table
| File | Action | Reason |
| --- | --- | --- |
| `ASCII_FLOW_LEGENDS.md` | move | Moved to `docs/reference/logging/`. Useful for visualization but host-specific and not authoritative |
| `CANONICAL_LOGGER_DESIGN_STANDARD.md` | rewrite | Kept in `docs/architecture/logging/`. Binding architectural blueprint. Replaced host-app reference with `maatify/event-logging` |
| `GLOBAL_LOGGING_RULES.md` | rewrite | Kept in `docs/architecture/logging/`. Binding rulebook. Replaced host-app reference with `maatify/event-logging` |
| `LOGGING_ASCII_OVERVIEW.md` | move | Moved to `docs/reference/logging/`. Useful for visualization but not binding. |
| `LOGGING_LIBRARY_STRUCTURE_CANONICAL.md` | move | Moved to `docs/reference/logging/`. Structural overview but uses host paths. Replaced `maatify/admin-control-panel` with `maatify/event-logging` and `app/Modules` with `src`. |
| `LOGGING_MODULE_BLUEPRINT.md` | rewrite | Kept in `docs/architecture/logging/`. Core architecture. Removed `App\` references, `App\Http\Controllers` and clarified it's a standalone package. |
| `LOG_DOMAINS_OVERVIEW.md` | rewrite | Kept in `docs/architecture/logging/`. Core domain knowledge. Replaced host-app reference with `maatify/event-logging` |
| `LOG_STORAGE_AND_ARCHIVING.md` | rewrite | Kept in `docs/architecture/logging/`. Binding storage rules. Replaced host-app reference with `maatify/event-logging` |
| `README.md` | rewrite | Kept in `docs/architecture/logging/`. Index of docs. Updated paths to point to reference docs and removed host-specific reference implementation. |
| `UNIFIED_LOGGING_DESIGN.md` | rewrite | Kept in `docs/architecture/logging/`. Core specification. Replaced host-app reference with `maatify/event-logging` |
| `unified-logging-system.ar.md` | keep | Original language doc. Kept as is as it is the canonical source of truth and contains no host-specific strings. |
| `unified-logging-system.en.md` | keep | English language doc. Kept as is as it is the canonical source of truth and contains no host-specific strings. |

## Remaining Integration-Readiness Gaps
The following remain gaps according to the Integration Readiness Roadmap:
* Phase 1 — Integration Surface Design
* Phase 2 — Factory / Provider Implementation
* Phase 3 — Primitive Read/Admin Viewing Support
* Phase 4 — Integration Documentation
* Phase 5 — Validation Gate
* Phase 6 — Final Integration Release Audit