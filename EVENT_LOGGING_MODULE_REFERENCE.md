# Event Logging Module Reference

`maatify/event-logging` is a standalone package-level module-equivalent for event logging. It follows the Maatify module standard where that standard fits a framework-agnostic Composer library, and documents the package-specific exceptions where forcing the application-module shape would weaken the logging design.

## Package contract

- Standalone Composer package.
- Host-agnostic: host applications provide identifiers and context; the package does not depend on host tables or runtime services.
- PDO-based persistence through domain-specific MySQL repositories.
- Framework-agnostic: no application container, HTTP framework, route layer, middleware, or runtime wiring is required by the package.
- Logging-domain isolation: each domain owns its contracts, DTOs, recorders, policies, exceptions, repositories, and SQL schema.

## Public namespaces

The package exposes `Maatify\EventLogging\` via PSR-4 autoloading. Public package namespaces are:

- `Maatify\EventLogging\AuthoritativeAudit\Command\*`
- `Maatify\EventLogging\AuthoritativeAudit\*`
- `Maatify\EventLogging\AuditTrail\Command\*`
- `Maatify\EventLogging\AuditTrail\*`
- `Maatify\EventLogging\SecuritySignals\Command\*`
- `Maatify\EventLogging\SecuritySignals\*`
- `Maatify\EventLogging\BehaviorTrace\Command\*`
- `Maatify\EventLogging\BehaviorTrace\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Command\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\*`
- `Maatify\EventLogging\DeliveryOperations\Command\*`
- `Maatify\EventLogging\DeliveryOperations\*`
- `Maatify\EventLogging\Common\*`

`Common` is limited to framework-neutral shared primitives such as clocks and sanitizers. It is not a shared logging domain.

## Isolated logging domains

1. **AuthoritativeAudit** — authoritative governance and security posture events that must be durably queued or fail closed.
2. **AuditTrail** — read-side and visibility events such as views, exports, navigation, and audit queries.
3. **SecuritySignals** — authentication, authorization, abuse, and anomaly signals.
4. **BehaviorTrace** — mutation-oriented operational behavior trace events.
5. **DiagnosticsTelemetry** — technical observability, diagnostics, and performance telemetry.
6. **DeliveryOperations** — asynchronous jobs, notifications, webhooks, provider delivery attempts, and lifecycle transitions.

## Domain boundaries

Each domain is intentionally separate. A domain-specific recorder accepts that domain's command or primitive convenience input, builds that domain's write DTO internally, writes only through that domain's writer contract/repository, and stores into only that domain's tables. Cross-domain helpers are limited to neutral utilities and must not become a cross-domain writer.

The package must not introduce any of the following:

- `GenericLogger`
- `GenericLogDTO`
- `GenericRecorder`
- A generic log table
- A cross-domain writer or repository

These are prohibited because the logging architecture requires independent failure semantics, retention policies, schema design, and review paths for each domain.

## Admin/Customer split exception

The module standard's `Admin/` and `Customer/` split is not applicable to this package. Event logging is not a UI or actor-facing business capability with separate admin and customer use cases. It is an infrastructure library consumed by host applications and backend services. Splitting it into `Admin` and `Customer` directories would create artificial layers and obscure the real logging-domain boundaries.

## Bootstrap and dependency binding exception

The module standard allows a `Bootstrap/{ModuleName}Bindings.php` entry point for host integration. This package intentionally does not provide project-specific bindings. Host applications should wire dependencies manually or through their own container by choosing the domain contracts, policies, clock, `PDO` connection, and repositories they need.

Optional package-provided bindings may be added later only if they remain framework-agnostic and do not assume a specific application container or runtime. No application runtime wiring is part of this package reference.

## Schema layout decision

The standard expects a package-level `schema/` directory. This package keeps the authoritative SQL files under each owning domain:

- `src/AuthoritativeAudit/Database/schema.maa_event_logging_authoritative_audit.sql`
- `src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql`
- `src/SecuritySignals/Database/schema.maa_event_logging_security_signals.sql`
- `src/BehaviorTrace/Database/schema.maa_event_logging_behavior_trace.sql`
- `src/DiagnosticsTelemetry/Database/schema.maa_event_logging_diagnostics_telemetry.sql`
- `src/DeliveryOperations/Database/schema.maa_event_logging_delivery_operations.sql`

The package-level `schema/README.md` indexes those locations instead of duplicating SQL files. This preserves domain ownership and prevents duplicate schema copies from drifting.

Canonical table names use the package prefix required by the module convention:

- `maa_event_logging_authoritative_audit_outbox`
- `maa_event_logging_authoritative_audit_log`
- `maa_event_logging_audit_trail`
- `maa_event_logging_security_signals`
- `maa_event_logging_behavior_trace`
- `maa_event_logging_diagnostics_telemetry`
- `maa_event_logging_delivery_operations`


## Command, DTO, recorder, and writer roles

Commands are the public input contracts for recording events. Each domain has its own command object under `Maatify\EventLogging\<Domain>\Command`; commands are `final readonly` and perform safe boundary validation such as required string checks, non-negative attempt numbers, and positive host-provided identifiers when present. Host applications may either construct these commands and pass them to `recordCommand()` or use the recorder's primitive `record()` convenience method, which constructs the domain command internally.

Write DTOs remain domain-specific recorder-to-writer transfer objects. They are built by recorders after command validation, policy normalization, metadata handling, timestamp assignment, and event id generation. Normal host application recording should not require manually constructing write DTOs.

Writers and repositories persist DTOs only. They do not apply policy, generate event ids, normalize actor/severity values, or decide fail-open/fail-closed behavior. This preserves the logging architecture rule that policy lives in the recorder layer and persistence remains a narrow domain-local write operation.

View DTOs and query DTOs remain read/output models. They are used by query interfaces and read repositories to return stored logging data without becoming public mutation input contracts.

This command/DTO split reconciles the module standard with the logging architecture: commands model public mutations, write DTOs model internal persistence transfer, and view DTOs model read output while each logging domain remains isolated.

## DTO serialization policy

DTOs are immutable `final readonly` value objects where possible. Package DTOs implement `JsonSerializable` when serialization is safe, structural, and non-display-oriented. Serialization preserves stored values, formats date-time values with `DATE_ATOM`, serializes domain enum interfaces through their `value()` method, and serializes nested DTOs through their own structural serializer.

DTO serialization must not add display labels, translated strings, HTML, route generation, authorization-dependent fields, or host-specific derived data.

At this time, no package DTO is intentionally excluded from `JsonSerializable`. If a future DTO carries a non-serializable resource, closure, stream, lazy host object, or display-oriented view model, it must document the exception here instead of adding unsafe serialization.

## Validation and failure semantics

### Fail-open command handling for non-authoritative domains

For AuditTrail, SecuritySignals, BehaviorTrace, DiagnosticsTelemetry, and DeliveryOperations, both `record()` and `recordCommand()` are fail-open. They catch `Throwable` across the full recording flow, including command construction from primitive input, command validation failures, policy normalization, DTO construction, writer/repository calls, and fallback logger failures. Command validation exceptions in these non-authoritative domains are swallowed by the recorder after optional best-effort PSR-3 fallback logging.

AuthoritativeAudit is the explicit exception. It remains fail-closed for governance and security posture events, so its recorder may throw validation or storage exceptions when required.


Recorders apply domain policies before storage. Policies decide whether an event is recordable and may normalize safe metadata. Storage exceptions remain domain-specific so hosts can choose fail-open or fail-closed behavior per domain.

The expected failure posture is domain-dependent:

- Authoritative audit events are security/governance critical and should be treated as fail-closed by host applications.
- Diagnostic, behavior, delivery, audit trail, and security-signal domains expose domain-specific exceptions so hosts can apply their own reliability posture without collapsing all failures into one generic logging error.

Validation belongs at domain boundaries. Commands carry public input; recorders apply policies and construct already-structured write DTOs; repositories enforce storage-specific failures without applying recording policy.

## Standard rules documented as package-specific exceptions

- **Admin/Customer folders:** Not applicable; logging domains are the correct public boundary.
- **Bootstrap bindings:** Not mandatory; the package remains framework-agnostic and host-wired.
- **Package-level SQL files:** Indexed from `schema/README.md`; authoritative schema stays domain-local.
- **Single module exception hierarchy:** Domain-specific storage exceptions are retained to preserve independent failure semantics.
