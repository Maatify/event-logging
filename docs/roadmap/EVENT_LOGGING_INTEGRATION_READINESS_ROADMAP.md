Event Logging Integration Readiness Roadmap

Current Status

The package is not final-release ready yet.

Previous release audit passed for the extracted core, but additional integration-readiness gaps were identified:

* Logging architecture docs were copied from the host project and must be cleaned.
* Some copied architecture docs may be reference-only or host-specific.
* Optional factories / providers / bindings are not yet available.
* Primitive read/admin viewing support must be verified and completed.
* Integration documentation is still incomplete.

⸻

Phase 0 — Logging Architecture Docs Cleanup

Owner: Jules

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

⸻

Phase 5 — Validation Gate

Owner: Jules

Required validation:

composer validate
composer install
find src -name "*.php" -exec php -l {} \;
vendor/bin/phpstan analyse -c phpstan.neon

If code was added, CI must pass.

⸻

Phase 6 — Final Integration Release Audit

Owner: Jules

Create:

docs/audits/FINAL_INTEGRATION_RELEASE_AUDIT.md

Required verdict:

* PASS

Release remains blocked until this audit passes with no blockers.
