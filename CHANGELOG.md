# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added full `maatify/persistence`-based Admin Query pagination path to `DiagnosticsTelemetry` preserving precise `v1.0.0` log properties.
- Added full `maatify/persistence`-based Admin Query pagination path to `DeliveryOperations` preserving precise `v1.0.0` log properties.

### Changed
- Converted array-shape return types in Admin read operations to fully typed immutable DTOs (`DiagnosticsTelemetryAdminPageResultDTO`, `DeliveryOperationsAdminPageResultDTO`) to comply with package standards.
- Re-architected test strategy to enforce strict separation between domain unit tests (using fake persistence) and real-MySQL integration tests with transaction boundaries, banning fragile PDO mocks.

### Removed
- Removed the deprecated and unused `DiagnosticsTelemetryQueryDTO` and `DiagnosticsTelemetryQueryMysqlRepository` wrappers as part of the post-v1.0 remediation cycle (No known usages across maintained host repositories).

## [1.0.1] - 2024-03-27

### Fixed
- Fixed strict type errors when handling null trace durations and null severity conversions in legacy PHP 8.1 environments.

## [1.0.0] - 2024-03-21

### Added
- Initial release of the `maatify/event-logging` domain contracts.
- Introduced `AuthoritativeAudit`, `AuditTrail`, `BehaviorTrace`, `SecuritySignals`, `DiagnosticsTelemetry`, and `DeliveryOperations` primitive write and read operations.
- Established strict exception boundaries mapping domain errors to HTTP-agnostic codes.

[Unreleased]: https://github.com/maatify/event-logging/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/maatify/event-logging/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/maatify/event-logging/releases/tag/v1.0.0
