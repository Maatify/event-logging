# Installation

`maatify/event-logging` is a standalone PHP library designed to integrate with any host application without being bound to a specific framework.

## Composer Requirements

You can install the package using Composer:

```bash
composer require maatify/event-logging
```

### System Requirements

- **PHP:** `^8.2`
- **Extensions required:**
  - `ext-json` (For safe metadata and payload decoding)
  - `ext-pdo` (For database integration)

## Schema Setup

This package does not execute automatic database migrations. The SQL schema files are located within the package and it is the host application's responsibility to run them using its own migration tooling.

You can find the comprehensive list of schema files in `schema/README.md` and the SQL files are stored locally within each domain's directory, for example: `src/AuthoritativeAudit/Database/`. The table names follow the `maa_event_logging_*` prefix.

## Host application integration

This library is highly decoupled and acts as an integration-agnostic package. Thus:

- **No Framework Requirement:** This package does not assume usage of Slim, Laravel, Symfony, PHP-DI, or any other framework/container.
- **No Host App Dependency:** The package maintains absolute isolation from host applications.
- **Host-Provided PDO:** The package requires a standard `PDO` instance for persistence, which must be created and provided by the host application.
- **No Automatic Routes/Controllers/UI:** This package does not automatically register any HTTP routes, controllers, or Admin UIs. The host application must implement all such features isolated from this package.
