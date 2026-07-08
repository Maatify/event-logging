# Maatify Event Logging

`maatify/event-logging` is a framework-agnostic standalone Composer package (not self-contained; it uses the Composer/runtime dependencies declared in composer.json) for isolated Maatify event logging domains. It is framework-agnostic and is wired by host applications through their own dependency setup.

## Logging domains

The package preserves six isolated event logging domains:

1. **AuthoritativeAudit** — governance/security posture logging with fail-closed semantics.
2. **AuditTrail** — reads, views, exports, and navigation visibility events.
3. **SecuritySignals** — authentication and security anomaly signals.
4. **BehaviorTrace** — operational activity and mutation-only behavior events.
5. **DiagnosticsTelemetry** — technical observability and diagnostic events.
6. **DeliveryOperations** — async jobs, notifications, webhooks, and delivery lifecycle events.

Shared primitives under `Maatify\EventLogging\Common` are limited to cross-domain utilities such as sanitizers and the `SystemClock` implementation. The clock contract source of truth is `maatify/shared-common` (`Maatify\SharedCommon\Contracts\ClockInterface`), and storage exception identity comes from `maatify/exceptions` (`SystemMaatifyException` with Maatify error codes). The package remains standalone from host applications and frameworks within the Maatify ecosystem, has no framework binding, and does not provide a generic logger, generic DTO, generic recorder, or shared generic log table.


## Recording API shape

Recorders accept domain-specific command objects through `recordCommand()` and also keep primitive `record()` convenience methods that construct the command internally. Host applications should not construct write DTOs for normal recording; write DTOs are internal recorder-to-writer transfer objects.

## Optional factories and provider

Host applications may keep constructing domain recorders manually, or use the optional framework-agnostic factories under `Maatify\EventLogging\Factory`. `EventLoggingProviderFactory::createDefault($pdo, $clock, $psrLogger)` creates a typed `EventLoggingProvider` service map with explicit domain accessors such as `authoritativeAudit()` and `auditTrail()`. The provider does not route by domain strings and does not expose a generic logging API.

`AuthoritativeAudit` remains fail-closed: the provider factory does not pass the optional PSR-3 logger to its factory as fallback storage. The PSR-3 logger is only forwarded to fail-open domains.

## Continuous Integration

This package uses GitHub Actions for continuous integration. PHPStan workflow is path-filtered and runs only when PHP source, Composer files, phpstan.neon, or workflow files change.

## Examples

For illustrative code samples covering different integration and usage scenarios, see the [Examples Coverage Plan](docs/examples/EXAMPLES_COVERAGE_PLAN.md).
Available examples in `examples/`:
- `00-bootstrap.php`
- `01-factory-provider.php`
- `02-manual-wiring.php`
- `03-authoritative-audit-record.php`
- `04-audit-trail-record.php`
- `05-security-signal-record.php`
- `06-behavior-trace-record.php`
- `07-diagnostics-telemetry-record.php`
- `08-delivery-operation-record.php`
- `09-admin-read-audit-trail.php`
- `10-admin-read-authoritative-audit.php`
- `11-cursor-pagination.php`
- `12-custom-policy.php`
- `13-psr-fallback-logger.php`

Note: These are plain PHP skeletons. DB-dependent examples require an explicit safe MySQL `DB_DSN` to be set. They are not executed automatically, contain no real credentials, and do not use SQLite.

## Installation

```bash
composer require maatify/event-logging
```

## Autoloading

The package exposes the `Maatify\EventLogging\` namespace via PSR-4 autoloading.


## Package documentation

### Integration Usage Guides
- [Installation](docs/integration/INSTALLATION.md)
- [Factory Usage](docs/integration/FACTORY_USAGE.md)
- [Manual Wiring](docs/integration/MANUAL_WIRING.md)
- [Admin Read Usage](docs/integration/ADMIN_READ_USAGE.md)

### Reference
- [Changelog](CHANGELOG.md)
- [Event Logging Module Reference](EVENT_LOGGING_MODULE_REFERENCE.md)
- [Testing Strategy](TESTING_STRATEGY.md)
- [Schema Layout](schema/README.md)
