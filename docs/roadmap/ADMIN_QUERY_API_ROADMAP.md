# Admin Query API Roadmap

**Status:** Deferred / Future Scope

## Overview
This roadmap outlines the future strategy for introducing a PHP-level **Admin Query API** within the `maatify/event-logging` package. The goal is to provide host applications with stable PHP contracts, DTOs, and domain-scoped query/read repositories that can be used as building blocks for their own administrative interfaces, without violating the architectural boundaries of the package as a standalone Composer package.

## Host Application Responsibilities (Strict Boundaries)
The `maatify/event-logging` package is strictly an infrastructure library. The Admin Query API is **PHP-level only**. The following capabilities must **always** remain the responsibility of the host application and will **never** be provided by this package:
- HTTP endpoints (API routes)
- Controllers and middleware
- Access control and permissions
- UI dashboards and frontend components
- CSV / PDF / Excel exports
- Generic analytics engines
- Generic log search
- Generic repository, generic DTO, or generic log table
- Actor and name resolution
- Localization and labels

## Future Admin Query API Scope
The planned Admin Query API will reside entirely at the PHP level. It builds carefully on the primitive domain query foundation (detailed in `ADMIN_READ_USAGE.md`) by extending the domain-scoped read capabilities without unnecessarily duplicating repositories, breaking the Public API, or converting the design into a generic logging API.
- **Domain-scoped admin listing/query contracts:** Stable PHP interfaces and package-level read models for querying domain-specific logs.
- **Dashboard summary query contracts:** Optimized, domain-scoped PHP read interfaces for retrieving statistical or summary data for host dashboards.
- **Reporting summary query contracts:** Domain-scoped PHP read interfaces designed to support host-level reporting needs.
- **Stable DTOs:** Data Transfer Objects specifically tailored for consumption by host admin panels.
- **Cursor/pagination/filter support:** Built-in PHP-level support for advanced pagination and filtering within specific domains.
- **Deferred / Optional Cross-domain admin search:** If implemented in the future, it must return strictly domain-tagged results, relying on individual domain boundaries without dissolving them into a generic repository or generic table.

## Architectural Constraints
All future additions in this scope must strictly adhere to the following library rules:
- **PHP Contracts Only:** All querying capabilities must be exposed strictly via PHP interfaces and DTOs.
- **No `Admin/Customer` Folder Split:** The package boundaries are the logging domains themselves (`AuthoritativeAudit`, `AuditTrail`, `SecuritySignals`, `BehaviorTrace`, `DiagnosticsTelemetry`, `DeliveryOperations`). Any admin-facing query capability must remain inside its respective domain boundary. A generic `Admin/` layer at the package level is strictly forbidden.
- **Framework-agnostic:** Must not depend on any specific framework features or containers.
- **Host-agnostic:** Must not assume any details about the host application's structure.
- **No JOINs/FKs:** Strict isolation; no database JOINs or Foreign Keys linking package tables to host application tables.
- **Domain boundaries first:** Queries must respect the isolation of the six core logging domains. The package will not expose generic catch-all logging APIs.
- **No generic abstractions:** The package will not introduce a generic log repository, generic log DTO, or generic log table.
- **Extend the Primitive Query Foundation:** Any new query API must complement existing domain-scoped read implementations without breaking backward compatibility.

## Proposed Implementation Phases

### Phase 1 — Single-Domain POC
Implement a small, domain-scoped enhancement within a single domain (e.g., `AuditTrail`) to validate the Admin Query API contracts.
- Must be contained within one domain.
- No generic abstraction, UI, HTTP endpoints, or permissions.
- Provide clear tests.
- Ensure strict backward compatibility with existing primitive read capabilities.

### Phase 2 — Apply Approved Domain-Scoped Query Pattern Across Remaining Domains
Use the Phase 1 single-domain POC to validate the Admin Query API shape. Once accepted, apply that approved domain-scoped pattern to the remaining logging domains.
- Proceed strictly domain-by-domain.
- Do not introduce a generic repository, generic DTO, generic log table, generic search layer, or generic Admin folder.
- Keep all work PHP-level only.
- Keep all work inside domain boundaries.
- No HTTP, controllers, routes, middleware, UI, permissions, exports, actor resolution, or localization.

### Phase 3 — Add Dashboard and Reporting Summary Query Contracts
Implement and document the PHP read models required to support domain-scoped host dashboard metrics and periodic reporting, strictly maintaining domain boundaries.

### Phase 4 — Add Integration Examples and Docs
Update the documentation and provide illustrative PHP examples demonstrating how a host application can wire the domain-scoped Admin Query API into their own HTTP/UI layer outside the package.

### Phase 5 — Validation with PHPStan/Tests/Examples
Ensure maximum static analysis coverage, complete unit and integration testing, and validation of all examples via syntax checks.
