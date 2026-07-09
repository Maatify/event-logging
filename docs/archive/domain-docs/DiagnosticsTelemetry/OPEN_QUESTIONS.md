# Open Questions (Resolved)

> **Resolution Status:** All questions in this document have been resolved.
> - **PDO Connection & Fallback Logging:** Documented in `docs/integration/MANUAL_WIRING.md`. The package does not manage PDO; the host provides it configured for `utf8mb4_unicode_ci`. Fallback logging relies on generic `LoggerInterface`.
> - **Query Repository Usage & Advanced Querying:** Addressed in `PUBLIC_API.md` and `EVENT_LOGGING_MODULE_REFERENCE.md`. Query repositories are strictly infrastructure adapters for host DI, and advanced query utilities remain purely application-level.
> - **UUID Dependency:** Documented in `EVENT_LOGGING_MODULE_REFERENCE.md` as an intentional explicit dependency; no internal generator is required.
>
> *This file is preserved for historical reference only.*

1. **PDO Connection**:
   - The module assumes a `PDO` instance is injected into the Writer. How should this connection be configured (e.g., specific charset, options) to ensure compatibility with `utf8mb4_unicode_ci` as per schema?

2. **Fallback Logging**:
   - The module uses `Psr\Log\LoggerInterface` for fallback logging. Is there a specific implementation expected, or is the generic interface sufficient?

3. **Query Repository Usage**:
   - The `DiagnosticsTelemetryQueryMysqlRepository` is implemented for "Readiness". How should it be exposed to the application (e.g. via a Service or directly)?

4. **UUID Dependency**:
   - The module uses `ramsey/uuid` as it is present in `composer.json`. If this dependency is removed in the future, an internal generator will need to be implemented.

5. **Advanced Querying**
   - Advanced querying (filters, search, pagination) is intentionally
     excluded from the canonical module.
   - Should the project provide optional, opinionated query utilities
     alongside the primitive reader, or should all advanced querying
     remain strictly application-level?
