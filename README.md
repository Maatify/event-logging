# Maatify Event Logging

`maatify/event-logging` is a standalone Composer library extracted by copy from the Athar Admin logging modules. It is intentionally not wired into Athar Admin during this phase, so the existing application runtime remains unchanged.

## Logging domains

The package preserves six isolated event logging domains:

1. **AuthoritativeAudit** — governance/security posture logging with fail-closed semantics.
2. **AuditTrail** — reads, views, exports, and navigation visibility events.
3. **SecuritySignals** — authentication and security anomaly signals.
4. **BehaviorTrace** — operational activity and mutation-only behavior events.
5. **DiagnosticsTelemetry** — technical observability and diagnostic events.
6. **DeliveryOperations** — async jobs, notifications, webhooks, and delivery lifecycle events.

Shared primitives live only under `Maatify\EventLogging\Common` and are limited to cross-domain utilities such as clocks and sanitizers. The package does not provide a generic logger, generic DTO, generic recorder, or shared generic log table.

## Installation

```bash
composer require maatify/event-logging
```

## Autoloading

The package exposes the `Maatify\EventLogging\` namespace via PSR-4 autoloading.
