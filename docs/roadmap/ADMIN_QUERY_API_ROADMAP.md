# Admin Query API Roadmap

**Status:** Deferred / Future Scope

## Overview
This roadmap outlines the future strategy for introducing a PHP-level **Admin Query API** within the `maatify/event-logging` package. The goal is to provide host applications with stable PHP contracts, DTOs, and repositories that can be used as building blocks for their own administrative interfaces, without violating the architectural boundaries of the package.

## Host Application Responsibilities (Strict Boundaries)
The `maatify/event-logging` package is strictly an infrastructure library. The following capabilities must **always** be implemented by the host application and will **never** be provided by this package:
- HTTP endpoints (API routes)
- Controllers and middleware
- Access control and permissions
- UI dashboards and frontend components
- CSV / PDF / Excel exports
- Actor and name resolution
- Localization and labels

## Future Admin Query API Scope
The planned Admin Query API will reside entirely at the PHP level and will provide:
- **Domain-scoped admin listing/query contracts:** Stable interfaces for querying domain-specific logs.
- **Cross-domain admin search:** Unified search contracts (where feasible) without degrading into a generic log repository.
- **Dashboard summary query contracts:** Optimized read interfaces for retrieving statistical or summary data for host dashboards.
- **Reporting summary query contracts:** Read interfaces designed to support host-level reporting needs.
- **Stable DTOs:** Data Transfer Objects specifically tailored for consumption by host admin panels.
- **Cursor/pagination/filter support:** Built-in PHP-level support for advanced pagination and filtering where appropriate.

## Architectural Constraints
All future additions in this scope must strictly adhere to the following library rules:
- **Framework-agnostic:** Must not depend on any specific framework features or containers.
- **Host-agnostic:** Must not assume any details about the host application's structure.
- **No JOINs/FKs:** Strict isolation; no database JOINs or Foreign Keys linking package tables to host application tables.
- **No mandatory `Admin/Customer` folder structure:** Internal package organization must remain domain-focused.
- **Domain boundaries first:** Queries must respect the isolation of the six core logging domains.
- **No generic logger/recorder/repository:** The package will not expose generic catch-all logging or querying paradigms.
- **Contract-driven:** Public query capabilities must have matching contracts/interfaces.

## Proposed Implementation Phases

### Phase 1 — Inventory Current Domain Query Capabilities
Audit existing primitive read capabilities and identify gaps needed for comprehensive admin querying.

### Phase 2 — Define Admin Query Contracts and DTO Boundaries
Establish the PHP interfaces, criteria objects, and DTOs required for listing and searching within and across domains.

### Phase 3 — Add Dashboard Summary Query Contracts
Implement and document the read models required to support host dashboard metrics (e.g., event counts, severities over time).

### Phase 4 — Add Reporting Summary Query Contracts
Develop the contracts needed to power periodic reporting (e.g., audit trails for a specific user over a timeframe).

### Phase 5 — Add Integration Examples and Docs
Update the documentation and provide illustrative PHP examples demonstrating how a host application can wire the Admin Query API into their own controllers and UI.

### Phase 6 — Validation with PHPStan/Tests/Examples
Ensure maximum static analysis coverage, complete unit and integration testing, and validation of all examples via syntax checks.
