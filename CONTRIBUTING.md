# Contributing to maatify/event-logging

Thank you for your interest in contributing to **maatify/event-logging**! We welcome contributions that help improve this package.

**maatify/event-logging** is a standalone, framework-agnostic Composer package. It is designed to be completely independent of any host application architecture or framework.

## Ways to Contribute

You can contribute to this project in several ways:

* **Reporting Bugs:** If you find a bug, please report it via GitHub Issues.
* **Improving Documentation/Examples:** Clarifying instructions, fixing typos, or adding new examples in the `docs/` and `examples/` directories.
* **Fixing Package-Local Behavior:** Resolving bugs or edge cases within the boundaries of the package itself.
* **Improving Tests/Static Analysis:** Adding test cases, improving coverage, or fixing PHPStan issues.
* **Improving MySQL/PDO Package-Owned Repositories:** Optimizing queries or fixing issues related strictly to our defined database schema interactions.
* **Improving Domain-Specific Logging Behavior:** Enhancing the logic for our six specific logging domains (AuthoritativeAudit, AuditTrail, SecuritySignals, BehaviorTrace, DiagnosticsTelemetry, DeliveryOperations).

## Project Structure

When navigating the project, you will encounter the following directories:

* `src/` — Contains all the production source code.
* `tests/` — Contains Unit, Integration, and Regression tests.
* `docs/` — Contains detailed architectural and integration documentation.
* `examples/` — Contains illustrative, standalone example scripts.
* `schema/` — Contains the SQL schema files required for the package to function.

## Running Tests

Before submitting a Pull Request, please ensure all tests and static analysis checks pass.

1. Install dependencies:
   ```bash
   composer install
   ```

2. Run tests:
   ```bash
   composer test
   ```

3. Run static analysis:
   ```bash
   vendor/bin/phpstan analyse -c phpstan.neon
   ```

## Architectural Rules

To maintain the integrity and standalone nature of this package, all contributions **must** adhere to the following architectural constraints:

* **No framework bindings:** The package must not rely on Laravel, Symfony, Slim, or any other framework.
* **No host app namespaces:** Code must not reference `App`, `Athar`, `EP4N`, or any project-specific namespaces.
* **No generic logger API:** The package intentionally forbids a single "generic" logger interface.
* **No generic recorder/repository:** Each domain has its own dedicated recorder and repository.
* **No shared generic `logs` or `event_logs` table:** Each domain corresponds to a specific `maa_event_logging_*` table.
* **MySQL/PDO only:** We strictly support MySQL-backed repositories.
* **Host provides PDO:** The consuming application is responsible for passing an active PDO connection.
* **No controllers/routes/UI/permissions:** This package does not provide admin screens, API routes, or UI components.
* **AuthoritativeAudit remains fail-closed:** Storage failures in the AuthoritativeAudit domain must throw exceptions and must not be swallowed.
* **Fail-open behavior stays at recorder boundary:** For non-authoritative domains, failure swallowing (fail-open) occurs only at the recorder layer, with optional fallback logging.
* **PSR-3 is an optional fallback:** A PSR-3 `LoggerInterface` is only used as a fallback mechanism for fail-open domains when database storage fails.

## Pull Request Guidelines

* **Keep PRs focused:** Try to solve one specific issue per Pull Request.
* **Update tests and docs:** If you change behavior, you must update the relevant tests and documentation.
* **Mention BC impact:** If your change breaks backward compatibility (BC), clearly state this in the PR description.
* **Do not change public API casually:** Changes to public interfaces and the `EventLoggingProvider` require careful consideration and discussion.
* **Update CHANGELOG:** For notable changes, please update `CHANGELOG.md` adhering to the "Keep a Changelog" format.

## Security

If you discover a security vulnerability, please **do not report it in a public issue**.

Instead, please review our [SECURITY.md](SECURITY.md) and report it privately via email to [support@maatify.com](mailto:support@maatify.com).
