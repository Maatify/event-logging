# Changelog

All notable changes to `maatify/event-logging` will be documented in this file.

## [1.0.0] - 2026-07-06

### Added
- Initial framework-agnostic standalone release of the Maatify Event Logging package (depends only on explicit Composer/runtime dependencies).
- Six isolated event logging domains: AuthoritativeAudit, AuditTrail, SecuritySignals, BehaviorTrace, DiagnosticsTelemetry, and DeliveryOperations.
- Domain-specific DTOs, contracts, recorders, policies, MySQL repositories, exceptions, enums, and schema files.
- Shared framework-agnostic utilities for clocks, URL sanitization, and metadata sanitization.
