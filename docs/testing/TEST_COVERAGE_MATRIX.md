# Test Coverage Matrix

This matrix provides a high-level overview of the intended test coverage spread across the different domains of the `maatify/event-logging` package.

| Feature / Domain | AuthoritativeAudit | AuditTrail | SecuritySignals | BehaviorTrace | DiagnosticsTelemetry | DeliveryOperations | Common |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Unit Tests** | | | | | | | |
| Command Validation | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| DTO Construction/Formatting | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Policy Enforcement/Sanitization| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Recorder Behavior Path | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Clock & Sanitizer Usage | N/A | N/A | N/A | N/A | N/A | N/A | ✅ |
| Mocked PDO Repository Logic | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| **Failure Semantics** | | | | | | | |
| Fail-Closed Storage Throw | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | N/A |
| Fail-Open Catch Throwable | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| PSR Fallback Usage | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| **Integration Tests (MySQL)**| | | | | | | |
| Schema Creation & DB Init | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Insert & Query Roundtrip | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Pagination (Cursor DESC) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Date Filters (`after`, `before`) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Actor Filters | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Request/Correlation Filters | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| Corrupt JSON Resilience | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | N/A |
| **Regression Tests** | | | | | | | |
| No Generic Classes | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| No App/Framework Coupling | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Legacy Read Methods BC | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | N/A |
