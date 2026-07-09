# Unified Logging Architecture

**Status:** CANONICAL
**Scope:** Defines the unified logging architecture, layering, authority boundaries, storage semantics, and forbidden patterns for the `maatify/event-logging` package.
**Audience:** Architects, Backend Developers, Reviewers

## 1. System Purpose

This document serves as the canonical source of truth for the event logging architecture provided by the `maatify/event-logging` package.

The architecture strictly enforces:
- Domain isolation (no generic loggers)
- Schema separation (no generic tables)
- Safe fail-open boundaries (except for authoritative auditing)
- Extractable, framework-agnostic execution

## 2. Core Package Scope

### 2.1 In Scope (Current Baseline)
- **Framework-Agnostic Library**: Designed as a standalone Composer package.
- **MySQL-Only Runtime Persistence**: Uses a host-provided PDO connection.
- **Strict Domain Contracts**: Dedicated DTOs, Recorders, and Repositories per domain.
- **Primitive Query APIs**: Simple structural reads only, primarily for archiving, export, or sequential processing.
- **One-Domain Rule**: An event is logged into one and only one domain based on its primary intent.

### 2.2 Explicitly Unsupported (Out of Scope)
- ❌ **Generic Loggers / Tables**: No `GenericLogDTO`, `GenericRecorder`, or single `logs` table.
- ❌ **Non-MySQL Storage**: MongoDB, Redis, SQLite, or other backend persistence modes are not supported and are explicitly out of scope for the runtime library.
- ❌ **Dual-Write Strategies**: Real-time dual writes (e.g., MySQL + Mongo) are excluded.
- ❌ **Advanced Querying**: No UI-grid queries, generic search, aggregations, joins, or generic reporting within the package.
- ❌ **Framework/Host Bindings**: No routes, controllers, middleware, or permissions are shipped within the package.
- ❌ **Required Archive Tables**: Archive tables are not required for baseline correctness.

## 3. The Golden Rule: The One-Domain Rule

Every recorded event belongs to **one domain only**, based on its **primary intent**.

If an action has multiple intents, do not duplicate the same payload. Instead, log separate distinct events with separate metadata into the respective domains.
