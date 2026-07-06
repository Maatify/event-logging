# Maatify Event Logging

`maatify/event-logging` is a standalone Composer library for isolated Maatify event logging domains. It is framework-agnostic and is wired by host applications through their own dependency setup.

## Logging domains

The package preserves six isolated event logging domains:

1. **AuthoritativeAudit** — governance/security posture logging with fail-closed semantics.
2. **AuditTrail** — reads, views, exports, and navigation visibility events.
3. **SecuritySignals** — authentication and security anomaly signals.
4. **BehaviorTrace** — operational activity and mutation-only behavior events.
5. **DiagnosticsTelemetry** — technical observability and diagnostic events.
6. **DeliveryOperations** — async jobs, notifications, webhooks, and delivery lifecycle events.

Shared primitives live only under `Maatify\EventLogging\Common` and are limited to cross-domain utilities such as clocks and sanitizers. The package does not provide a generic logger, generic DTO, generic recorder, or shared generic log table.


## Recording API shape

Recorders accept domain-specific command objects through `recordCommand()` and also keep primitive `record()` convenience methods that construct the command internally. Host applications should not construct write DTOs for normal recording; write DTOs are internal recorder-to-writer transfer objects.

## Installation

```bash
composer require maatify/event-logging
```

## Autoloading

The package exposes the `Maatify\EventLogging\` namespace via PSR-4 autoloading.


## Package documentation

- [Changelog](CHANGELOG.md)
- [Event Logging Module Reference](EVENT_LOGGING_MODULE_REFERENCE.md)
- [Testing Strategy](TESTING_STRATEGY.md)
- [Schema Layout](schema/README.md)
