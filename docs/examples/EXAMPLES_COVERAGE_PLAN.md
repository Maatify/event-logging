# Examples Coverage Plan

This document outlines the planned examples to be created for the `maatify/event-logging` package.

All examples MUST follow these rules:
- Be plain PHP only.
- No framework-specific bindings (e.g., Slim, Laravel, Symfony, PHP-DI).
- No host application helpers.
- No real credentials or secrets.
- Use safe dummy DSNs only.
- Clearly marked as either runnable or just a skeleton/illustrative.

## Directory Structure

Examples will be located in the `examples/` directory at the project root.

```
examples/
  00-bootstrap.php
  01-factory-provider.php
  02-manual-wiring.php
  03-authoritative-audit-record.php
  04-audit-trail-record.php
  05-security-signal-record.php
  06-behavior-trace-record.php
  07-diagnostics-telemetry-record.php
  08-delivery-operation-record.php
  09-admin-read-audit-trail.php
  10-admin-read-authoritative-audit.php
  11-cursor-pagination.php
  12-custom-policy.php
  13-psr-fallback-logger.php
```

## Example Details

### `00-bootstrap.php`
- **Purpose:** Setup a dummy environment (PDO, Clock, PSR-3 Logger) used by other runnable examples.
- **Dependencies:** `PDO`, `Maatify\SharedCommon\Contracts\ClockInterface` (implementation), `LoggerInterface` (implementation).
- **Demonstrates:** How a host application might prepare the dependencies required by the package.
- **Must Not Demonstrate:** Real credentials. Use a safe dummy MySQL test DSN from environment variables. SQLite must not be presented as a compatible runtime for the package MySQL repositories.
- **Safety Notes:** Do not include real passwords.
- **Type:** Runnable (creates dummy objects) if a test MySQL DSN is configured, otherwise illustrative. DB-dependent examples should be marked illustrative unless a test MySQL DSN is available.

### `01-factory-provider.php`
- **Purpose:** Demonstrate using `EventLoggingProviderFactory` to create the provider.
- **Dependencies:** `00-bootstrap.php`
- **Demonstrates:** Passing PDO, Clock, and optional PSR logger to `createDefault()` and accessing a domain recorder (e.g., `auditTrail()`).
- **Must Not Demonstrate:** Generic routing or framework DI container setup.
- **Safety Notes:** N/A
- **Type:** Runnable.

### `02-manual-wiring.php`
- **Purpose:** Demonstrate how to wire a single domain (e.g., BehaviorTrace) manually without the factory.
- **Dependencies:** `00-bootstrap.php`
- **Demonstrates:** Instantiating the Repository, Policy, and Recorder directly.
- **Must Not Demonstrate:** Incorrect wiring (e.g., skipping policy).
- **Safety Notes:** N/A
- **Type:** Runnable.

### `03-authoritative-audit-record.php`
- **Purpose:** Show how to record a fail-closed authoritative audit event.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Using `recordCommand()` with strict required fields.
- **Must Not Demonstrate:** Fallback logging.
- **Safety Notes:** Illustrates fail-closed behavior.
- **Type:** Illustrative/Runnable depending on PDO setup.

### `04-audit-trail-record.php`
- **Purpose:** Show how to record an audit trail event.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Primitive `record()` convenience method and `recordCommand()`.
- **Must Not Demonstrate:** Authoritative data.
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.

### `05-security-signal-record.php`
- **Purpose:** Show how to record a security signal.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Recording anomaly signals (e.g., failed logins).
- **Must Not Demonstrate:** Storing passwords or raw tokens.
- **Safety Notes:** Explicitly shows sanitization of sensitive data.
- **Type:** Illustrative/Runnable.

### `06-behavior-trace-record.php`
- **Purpose:** Show how to record a behavior trace.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Recording mutation events (e.g., entity updated).
- **Must Not Demonstrate:** Read events (belongs to AuditTrail).
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.

### `07-diagnostics-telemetry-record.php`
- **Purpose:** Show how to record technical diagnostics.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Recording system events (e.g., queue worker started, slow query).
- **Must Not Demonstrate:** Business events.
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.

### `08-delivery-operation-record.php`
- **Purpose:** Show how to record delivery lifecycle events.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Recording webhook, email, or async job statuses.
- **Must Not Demonstrate:** Synchronous business events.
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.

### `09-admin-read-audit-trail.php`
- **Purpose:** Show how to query the audit trail for admin UI.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Using `AuditTrailQueryInterface` with filters (e.g., `actor_id`).
- **Must Not Demonstrate:** Creating a generic admin UI controller.
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.

### `10-admin-read-authoritative-audit.php`
- **Purpose:** Show how to query authoritative audit logs.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Using `AuthoritativeAuditQueryInterface` (notably missing `requestId` filter by design).
- **Must Not Demonstrate:** Generic querying.
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.

### `11-cursor-pagination.php`
- **Purpose:** Demonstrate cursor-based pagination for querying logs.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** Using `cursorOccurredAt`, `cursorId`, and `limit` correctly to fetch the next page.
- **Must Not Demonstrate:** Offset pagination.
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.

### `12-custom-policy.php`
- **Purpose:** Show how to create and inject a custom policy.
- **Dependencies:** `00-bootstrap.php`
- **Demonstrates:** Implementing `AuditTrailPolicyInterface` and wiring it manually.
- **Must Not Demonstrate:** Bypassing security rules.
- **Safety Notes:** N/A
- **Type:** Illustrative.

### `13-psr-fallback-logger.php`
- **Purpose:** Demonstrate fail-open behavior with a PSR-3 fallback logger.
- **Dependencies:** `00-bootstrap.php`, Provider or Manual Wiring.
- **Demonstrates:** A failing PDO connection falling back to the injected PSR-3 logger for non-authoritative domains.
- **Must Not Demonstrate:** Fallback logging for AuthoritativeAudit.
- **Safety Notes:** N/A
- **Type:** Illustrative/Runnable.
