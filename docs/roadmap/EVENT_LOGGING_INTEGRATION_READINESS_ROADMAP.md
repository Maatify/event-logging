Event Logging Integration Readiness Roadmap

Current Status

The package is not final-release ready yet.

Previous release audit passed for the extracted core, but additional integration-readiness gaps were identified:

* Logging architecture docs were copied from the host project and must be cleaned.
* Some copied architecture docs may be reference-only or host-specific.
* Optional factories / providers / bindings are not yet available.
* Primitive read/admin viewing support must be verified and completed.
* Integration documentation is still incomplete.


## Module Building Standard Alignment

- **Required root files:** Deferred to Phase 4
- **Namespace/package boundaries:** Applicable now (Phase 1)
- **Schema rules:** Deferred to Phase 4
- **Exception rules:** Mostly not applicable to fail-open write paths; verify existing package exception policy and any read/query exceptions during Phase 3 and Final Audit.
- **Command/DTO rules:** Deferred to Phase 3
- **Repository/read rules:** Deferred to Phase 3
- **Bootstrap/DI rules:** Applicable now (Phase 1)
- **PHPStan max:** Deferred to Phase 5
- **Public contracts/interfaces:** Applicable now (Phase 1)
- **Documentation completeness:** Deferred to Phase 4
- **Final “module is not done until” checklist:** Deferred to Phase 6

⸻

Phase 0 — Logging Architecture Docs Cleanup

Owner: Jules
Status: Complete

Goal:

Review the copied docs under:

docs/architecture/logging/

Tasks:

* Compare copied docs against the original architecture intent.
* Remove files that are not needed inside the standalone package.
* Keep only docs that are useful as package architecture references.
* Remove or rewrite host-project references:
    * Athar Admin
    * athar-admin
    * maatify/admin-control-panel
    * app/Modules
    * Slim
    * project-specific runtime wording
* Move non-binding visualization docs, if kept, to:

docs/reference/logging/

Expected output:

* Cleaned docs/architecture/logging/
* Optional docs/reference/logging/
* Updated roadmap status
* Audit note explaining what was kept, moved, or removed

⸻

Phase 1 — Integration Surface Design

Owner: Jules
Status: Complete

Goal:

Define what the package should expose for easy usage without becoming host-specific.

Required decisions:

* Factory layer shape
* Optional provider / bindings map
* Whether to expose an EventLoggingManager
* How each domain logger should be constructed
* What remains the host application’s responsibility

Rules:

* No Slim bindings
* No PHP-DI-specific bindings
* No framework-specific service provider
* No host app runtime assumptions

Checkpoints (Module Building Standard):
* Explicitly cover: Whether Bootstrap/DI belongs in this package
* Explicitly cover: Whether optional bindings are framework-agnostic
* Explicitly cover: Which public services/repositories need contracts
* Explicitly cover: Whether EventLoggingManager is needed or avoided
* Verify: Namespace/package boundaries

Expected design artifacts:

docs/architecture/INTEGRATION_SURFACE_DESIGN.md
docs/architecture/FACTORY_AND_PROVIDER_DESIGN.md

⸻

Phase 2 — Factory / Provider Implementation

Owner: Codex

Goal:

Implement framework-agnostic construction helpers.

Expected code:

src/Factory/
src/Provider/

Expected capabilities:

* Build each domain recorder from PDO + Clock + optional PSR logger
* Allow custom policies where appropriate
* Provide an optional map/object containing all available logging components
* Avoid container-specific logic

Must not introduce:

* GenericLogger
* GenericDTO
* GenericRecorder
* Generic log table
* Host-project bindings

⸻

Phase 3 — Primitive Read/Admin Viewing Support

Owner: Jules first, then Codex if code gaps exist

Goal:

Verify and complete primitive read support needed for admin viewing.

Important:

This is not CRUD and not UI.

The package should provide primitive read/query contracts only:

* cursor pagination
* date range
* actor filter
* entity/target filter
* action/event key filter
* request_id
* correlation_id

Host application remains responsible for:

* controllers
* routes
* permissions
* admin UI
* exports
* complex analytics

Expected docs:

docs/architecture/ADMIN_READ_SUPPORT.md

Checkpoints (Module Building Standard):
* Explicitly cover: Reader contracts
* Explicitly cover: Query DTOs
* Explicitly cover: Cursor DTOs
* Explicitly cover: View DTOs
* Explicitly cover: MySQL query repositories
* Explicitly cover: Pagination style decision
* Verify: Command/DTO rules
* Verify: Repository/read rules
* Verify existing domain-specific exception classes.
* Verify fail-open swallowing exists only at the Recorder boundary.
* Verify repositories/infrastructure do not swallow Throwable.
* Verify AuthoritativeAudit remains fail-closed.
* Verify read/query exceptions follow MODULE_BUILDING_STANDARD where applicable.
* Verify named constructors and RuntimeException inheritance where exceptions are created directly.

Expected code if missing:

* Reader interfaces
* Query DTOs
* Cursor DTOs
* View DTOs
* MySQL query repositories

⸻

Phase 4 — Integration Documentation

Owner: Jules

Goal:

Document how to actually use the package.

Required docs:

docs/usage/INSTALLATION.md
docs/usage/FACTORY_USAGE.md
docs/usage/MANUAL_WIRING.md
docs/usage/ADMIN_READ_USAGE.md

Must explain:

* composer install
* schema setup
* PDO wiring
* factory usage
* manual construction
* custom policy injection
* fallback logger behavior
* reader/query usage
* what the host app must implement itself

Checkpoints (Module Building Standard):
* Verify: Required root files
* Verify: Schema rules
* Verify: Documentation completeness

⸻

Phase 5 — Validation Gate

Owner: Jules

Required validation:

composer validate
composer install
find src -name "*.php" -exec php -l {} \;
vendor/bin/phpstan analyse -c phpstan.neon

If code was added, CI must pass.

Checkpoints (Module Building Standard):
* Verify: PHPStan max

⸻

Phase 6 — Final Integration Release Audit

Owner: Jules

Create:

docs/audits/FINAL_INTEGRATION_RELEASE_AUDIT.md

Required verdict:

* PASS

Checkpoints (Module Building Standard):
* Final audit against docs/standards/MODULE_BUILDING_STANDARD.md
* Verify: Final "module is not done until" checklist

Release remains blocked until this audit passes with no blockers.
