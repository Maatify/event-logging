# Changelog

All notable changes to `maatify/event-logging` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added the separate AuthoritativeAudit Admin Query public API with package-owned request/result DTOs and MySQL repository.
- Added Unit, Regression, and strict real-MySQL Integration coverage for AuthoritativeAudit Admin Query pagination and primitive cursor compatibility.
- Added the separate AuditTrail Admin Query public API with package-owned request/result DTOs and MySQL repository.
- Added the separate BehaviorTrace Admin Query public API with package-owned request/result DTOs and MySQL repository.
- Added the separate SecuritySignals Admin Query public API with package-owned request/result DTOs and MySQL repository.
- Added `maatify/persistence` for deterministic offset pagination mechanics.
- Added Unit, Regression, and MySQL Integration coverage for AuditTrail Admin Query pagination.
- Added Unit, Regression, and MySQL Integration coverage for BehaviorTrace Admin Query pagination and primitive cursor compatibility.
- Added Unit, Regression, and strict MySQL Integration coverage for SecuritySignals Admin Query pagination and primitive cursor compatibility.

### Changed
- Corrected AuthoritativeAudit primitive `find()` cursor placeholders for native PDO prepared statements without changing v1 semantics.
- Updated internal mapper, descriptor, repository, and documentation states for AuthoritativeAudit Admin Query.
- Extracted AuditTrail row hydration into a shared internal mapper while preserving primitive query behavior.
- Extracted BehaviorTrace policy-aware row hydration into a shared internal mapper while preserving primitive query behavior.
- Extracted SecuritySignals row hydration into a policy-free shared internal mapper while preserving primitive query behavior.
- Corrected BehaviorTrace primitive `find()` cursor placeholders for native PDO prepared statements without changing cursor semantics.
- Corrected SecuritySignals primitive `find()` cursor placeholders for native PDO prepared statements without changing cursor semantics.
- Updated AuditTrail Admin Query blueprint, roadmap, package reference, and integration documentation to implementation status.
- Updated BehaviorTrace Admin Query blueprint, package reference, module README, and integration documentation to implementation status.
- Updated SecuritySignals Admin Query blueprint, roadmap, package reference, module README, and integration documentation to implementation status after completing the follow-up verification contract.

### Removed
- Removed the exactly seven superseded AuthoritativeAudit post-v1 pagination wrapper artifacts.
- Removed superseded AuditTrail post-v1 pagination wrapper artifacts that were not protected `v1.0.0` contracts.
- Removed superseded BehaviorTrace post-v1 pagination wrapper artifacts that were not protected `v1.0.0` contracts.
- Removed superseded SecuritySignals post-v1 pagination wrapper artifacts that were not protected `v1.0.0` contracts.

### Documentation
- Drafted AuditTrail Admin Query POC blueprint (`ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md`) defining the proposed replacement architecture. No Runtime, Composer, schema, or test behavior changed.

### Added
- Added optional pure-PHP DI binding helper for host applications that want convenience container wiring without a mandatory DI dependency.

### Changed
- Polished Composer metadata (`composer.json`) to accurately reflect package scope, requirements, and dependencies.

### Documentation
- Removed superseded Admin Query cursor-wrapper audit documents.
- Removed partial domain `PUBLIC_API.md` files.
- Aligned current Runtime, primitive-read, integration, and roadmap documentation with the approved post-v1 architecture.
- Confirmed that this documentation cleanup introduces no Runtime, Composer, schema, or test behavior change.

- Completed Phase 1 Admin Query Runtime and persistence compatibility inventory (strictly documentation and audit).
- Unified future Admin Query API architecture (`ADMIN_QUERY_API_ARCHITECTURE.md`) and roadmap.
- Documented explicit separation between current primitive read APIs and the target Admin pagination.
- Recorded future dependency on `maatify/persistence` for standardized pagination mechanics while explicitly deferring implementation until owner approval.
- Added `DI_BINDINGS.md` integration guide and `14-di-bindings.php` example to document optional DI container wiring.
- Clarified that framework-agnostic core wiring can be manual or use optional convenience DI bindings.
- Applied professional release-grade polish to `README.md`.
- Completed final documentation audit and instituted documentation quality gate.
- Standardized package structure and wording across examples, schema index, module references, and integration guides for release readiness.

### Security
- Added `SECURITY.md` for explicit security policies and vulnerability reporting guidelines.

## [1.0.0] - 2026-07-06

### Summary
Initial stable release of `maatify/event-logging` under the strictly isolated `Maatify\EventLogging\` namespace. This is a framework-agnostic standalone Composer package with explicit dependencies (e.g., `ext-json`, `ext-pdo`, `psr/log`, `maatify/shared-common`, `maatify/exceptions`) and is intentionally not "dependency-free".

### Added
- Six rigorously isolated event logging domains: `AuthoritativeAudit`, `AuditTrail`, `SecuritySignals`, `BehaviorTrace`, `DiagnosticsTelemetry`, and `DeliveryOperations`.
- Domain-owned MySQL persistence using explicit PDO instances.
- Domain-specific recording models utilizing structured commands, recorders, and internal write DTO transfers.
- Primitive, deterministic read and query support via cursor-based MySQL query repositories, query DTOs, and view DTOs.
- Clear factory (`src/Factory/`) and provider (`src/Provider/`) layers for seamless, optional, framework-agnostic host application wiring.

### Changed
- Enforced strict alignment with Maatify core architecture, integrating `SystemMaatifyException` from `maatify/exceptions` for storage-related failures.
- Adopted `ClockInterface` exclusively from `maatify/shared-common` in place of internal implementations.

### Security
- Preserved AuthoritativeAudit fail-closed behavior, ensuring storage failures are surfaced instead of being swallowed or redirected to PSR-3 fallback logging.
- Structured non-authoritative domains to fail-open securely at the recorder boundary, with optional PSR-3 fallback logging capabilities.

### Validation
- Banned direct use of `RuntimeException` for storage/read/query exceptions, replacing them with strongly typed domain or system-level exceptions.

### Guarantees
- Supports MySQL-backed repositories only; SQLite is explicitly unsupported and must not be presented as a compatible runtime.
- Completely devoid of generic tables (e.g., `logs`, `event_logs`).
- Operates entirely free of framework-specific bindings and isolated from host application namespaces.
- Contains absolutely zero UI components, admin controllers, route handling, permissions logic, or generic analytics inside the package boundary.

[Unreleased]: https://github.com/Maatify/event-logging/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Maatify/event-logging/releases/tag/v1.0.0
