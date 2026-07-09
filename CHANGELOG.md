# Changelog

All notable changes to `maatify/event-logging` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - Release Preparation

### Changed
- Polished Composer metadata (`composer.json`) to accurately reflect package scope, requirements, and dependencies.

### Documentation
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
