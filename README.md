<div align="center">

# Maatify Event Logging

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![Latest Version](https://img.shields.io/packagist/v/maatify/event-logging.svg)](https://packagist.org/packages/maatify/event-logging)
[![PHP Version](https://img.shields.io/packagist/php-v/maatify/event-logging.svg)](https://packagist.org/packages/maatify/event-logging)
[![License](https://img.shields.io/packagist/l/maatify/event-logging.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%20Max-4E8CAE)](https://github.com/Maatify/event-logging)

[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/event-logging)](https://packagist.org/packages/maatify/event-logging)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/event-logging)](https://packagist.org/packages/maatify/event-logging)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)
[![Install](https://img.shields.io/badge/Install-composer%20require%20maatify%2Fevent--logging-blue)](https://packagist.org/packages/maatify/event-logging)

[![Changelog](https://img.shields.io/badge/Changelog-View-blue.svg)](CHANGELOG.md)
[![Package Reference](https://img.shields.io/badge/Reference-Read-blue.svg)](EVENT_LOGGING_MODULE_REFERENCE.md)
[![Security Policy](https://img.shields.io/badge/Security-Policy-blue.svg)](SECURITY.md)
[![Contributing Guide](https://img.shields.io/badge/Contributing-Guide-blue.svg)](CONTRIBUTING.md)

`maatify/event-logging` is a framework-agnostic standalone Composer package for registering event logging domains within the Maatify ecosystem.

This package provides strict domain isolation, MySQL persistence, and fail-open/fail-closed semantics without mandatory framework bindings. It intentionally relies on explicit Composer and runtime dependencies to operate autonomously from host applications and frameworks.

</div>

---

## 🚀 Key Features

* **Six Isolated Logging Domains**: Explicit boundaries for audit, tracing, signals, and operations.
* **Domain-Specific Recorders**: Tailored commands, DTOs, and repositories per domain.
* **MySQL-Only Persistence**: Direct database persistence through domain-owned repositories.
* **AuthoritativeAudit Semantics**: Fail-closed governance logging.
* **Fail-Open Boundaries**: Non-authoritative domains can accept a PSR-3 fallback logger.
* **Optional Framework-Agnostic Provider and Bindings**: Built-in optional factories and pure-PHP DI binding helpers for wiring dependencies.
* **Primitive Cursor-Based Read APIs**: Dedicated, stable read/query capabilities.
* **No Mandatory Framework Bindings**: Optional bindings are pure PHP convenience helpers and do not add framework runtime dependencies.
* **No Generic Logging API**: Excludes shared generic loggers, generic log tables, or unified repositories.

---

## 📋 Requirements

* PHP `^8.2`
* `ext-json`
* `ext-pdo`

**Composer/Runtime Dependencies:**
* `psr/log`
* `ramsey/uuid`
* `maatify/exceptions`
* `maatify/shared-common`

---

## 📦 Installation

```bash
composer require maatify/event-logging
```

---

## 📖 Usage

### Factory / Provider Wiring

Host applications provide their own dependencies (PDO, Clock, PSR-3 Logger) to instantiate the domain provider:

```php
use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\Common\SystemClock;

$pdo = new \PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
$clock = new SystemClock();
$psrLogger = new \Monolog\Logger('event-logging-fallback'); // Optional for fail-open domains

// Create the provider service map
$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $psrLogger);

// Typed accessors enforce strict domain API boundaries
$auditTrail = $provider->auditTrail();
```

*(See [`01-factory-provider.php`](examples/01-factory-provider.php) for manual factory/provider wiring. Hosts that prefer DI can also import the optional pure-PHP `Maatify\EventLogging\Bootstrap\EventLoggingBindings::definitions()` helper; this helper is not required and does not introduce framework-specific behavior.)*

### Domain Recorder Usage

Recording an authoritative audit (fail-closed, requires robust persistence):

```php
$authoritativeAudit = $provider->authoritativeAudit();

// Using the primitive convenience record method
$authoritativeAudit->record(
    action: 'update_permissions',
    targetType: 'user',
    targetId: 123,
    riskLevel: 'HIGH',
    actorType: 'admin',
    actorId: 1,
    payload: ['role' => 'superuser'],
    correlationId: 'abc-123'
);
```

*(See [`03-authoritative-audit-record.php`](examples/03-authoritative-audit-record.php) and [`04-audit-trail-record.php`](examples/04-audit-trail-record.php) for broader examples.)*

---

## 🏗️ Logging Domains

The package maintains six separated event domains:

1. **AuthoritativeAudit** — Governance/security posture logging with fail-closed semantics.
2. **AuditTrail** — Reads, views, exports, and navigation visibility events.
3. **SecuritySignals** — Authentication and security anomaly signals.
4. **BehaviorTrace** — Operational activity and mutation-only behavior events.
5. **DiagnosticsTelemetry** — Technical observability and diagnostic events.
6. **DeliveryOperations** — Async jobs, notifications, webhooks, and delivery lifecycle events.

---

## 🛡️ Architecture Guarantees

* **Framework-Agnostic**: Operates without any dependency on external framework containers or routes.
* **Standalone Execution**: Complete isolation from host application codebases.
* **Domain Isolation**: Every logging domain has exclusive ownership over its DTOs, tables, and policies.
* **Host-Provided PDO**: Database connections must be injected; the package does not manage connection lifecycles.
* **MySQL-Only**: Relies purely on MySQL schema without SQLite fallback support.
* **Explicit Dependencies Only**: Avoids zero-dependency assumptions; explicit runtime requirements ensure robustness.
* **No Generic Paradigms**: The package does not expose a catch-all logger, generic recorder, generic repository, or shared generic log table. PSR-3 is used only as an optional fallback logger for fail-open domains.

---

## 🧪 Examples

The `examples/` directory contains plain PHP illustrative skeletons covering integration scenarios. See the [Examples Coverage Plan](docs/examples/EXAMPLES_COVERAGE_PLAN.md) for full context.

* [`01-factory-provider.php`](examples/01-factory-provider.php)
* [`02-manual-wiring.php`](examples/02-manual-wiring.php)
* [`03-authoritative-audit-record.php`](examples/03-authoritative-audit-record.php)
* [`09-admin-read-audit-trail.php`](examples/09-admin-read-audit-trail.php)
* [`11-cursor-pagination.php`](examples/11-cursor-pagination.php)
* [`13-psr-fallback-logger.php`](examples/13-psr-fallback-logger.php)

---

## 📚 Documentation

* [Public API](PUBLIC_API.md)
* [Event Logging Module Reference](EVENT_LOGGING_MODULE_REFERENCE.md)
* [Schema Layout](schema/README.md)
* [Testing Strategy](TESTING_STRATEGY.md)

### Architecture
* [Unified Logging Architecture](docs/architecture/LOGGING_ARCHITECTURE.md)
* [Logging Domain Rules](docs/architecture/LOGGING_DOMAIN_RULES.md)
* [CI Workflow Standard](docs/standards/CI_WORKFLOW_STANDARD.md)
* [Storage & Schema Guarantees](docs/architecture/STORAGE_AND_SCHEMA.md)
* [Deferred Scope & Future Considerations](docs/architecture/DEFERRED_SCOPE.md)
* [Authoritative Audit Pipeline](docs/architecture/AUTHORITATIVE_AUDIT_PIPELINE.md)
* [Admin Query API Roadmap](docs/roadmap/ADMIN_QUERY_API_ROADMAP.md)

### Integration Guides
* [Installation Guide](docs/integration/INSTALLATION.md)
* [Factory Usage](docs/integration/FACTORY_USAGE.md)
* [Manual Wiring](docs/integration/MANUAL_WIRING.md)
* [DI Bindings](docs/integration/DI_BINDINGS.md)
* [Admin Read Usage](docs/integration/ADMIN_READ_USAGE.md)

---

## ✅ Quality Status

* PHP 8.2+
* PHPStan Level Max
* PHPUnit testing available
* GitHub Actions CI validated

---

## 🪪 License

This package is licensed under the MIT License.
See the [LICENSE](LICENSE) file for details.

---

## 👤 Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
