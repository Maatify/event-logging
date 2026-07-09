# Storage & Schema Guarantees

**Status:** CANONICAL
**Scope:** Defines the baseline storage schema, naming conventions, and constraints for the `maatify/event-logging` package.

---

## 1. Canonical Schema Naming

The package enforces a strict naming convention for all tables. The prefix `maa_event_logging_` is mandatory to ensure isolation from host application schemas. Legacy host table names are strictly historical and must not be used as canonical names.

### The Canonical Tables
1. `maa_event_logging_authoritative_audit_outbox`
2. `maa_event_logging_authoritative_audit_log`
3. `maa_event_logging_audit_trail`
4. `maa_event_logging_security_signals`
5. `maa_event_logging_behavior_trace`
6. `maa_event_logging_diagnostics_telemetry`
7. `maa_event_logging_delivery_operations`

## 2. Storage Requirements

- **Engine:** MySQL (InnoDB or compatible).
- **Connection:** The package does not manage database connections. A configured `PDO` instance (using the `utf8mb4_unicode_ci` schema) must be provided by the host application.
- **Alternative RDBMS:** SQLite is explicitly unsupported as a runtime or test storage mechanism. The package uses disposable MySQL databases for integration testing.
- **Non-RDBMS Storage:** MongoDB, Redis, etc., are explicitly unsupported and are out of scope for this package.

## 3. Schema Design Rules

- **No Generic Tables:** There is no single `logs` or `event_logs` table. Every domain maps to a specific table with dedicated columns.
- **Column-Searchable Structure:** Key domain attributes (e.g., actor ID, event type, correlation ID) are persisted as actual typed columns to support primitive indexing.
- **JSON Metadata:** Arbitrary contextual data is stored in dedicated JSON-typed columns, not overloaded into generic string fields.

## 4. Query Semantics

The repositories in this package provide only **primitive, domain-specific read/query APIs**.
These are intended strictly for:
- Archiving
- Sequential processing (e.g., outbox consumers)
- Simple exports

**Forbidden Operations:**
- Advanced UI-grid querying
- Multi-table joins
- Aggregations and analytics
- Generic search across domains
