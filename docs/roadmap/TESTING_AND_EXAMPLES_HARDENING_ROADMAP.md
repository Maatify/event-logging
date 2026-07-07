# Testing & Examples Hardening Roadmap

**Context:** The `maatify/event-logging` package is Integration Release Ready. This document is a post-readiness quality hardening plan before a future stable v1.0 release. It must NOT reopen the integration release gate. This roadmap plans for strong, useful, professional test coverage and examples coverage across the entire package.

## 1. Test Infrastructure

**Recommended Test Tooling:**
- **Framework:** **PHPUnit** is recommended over Pest. This is a framework-agnostic package meant for wide compatibility, and PHPUnit is the standard for low-level library testing. It provides strict class-based testing which aligns well with the strict domain isolation architecture.
- **Composer dev dependencies:** `phpunit/phpunit` (v10 or v11 depending on PHP 8.2 support).
- **Autoload-dev setup:**
  ```json
  "autoload-dev": {
      "psr-4": {
          "Maatify\\EventLogging\\Tests\\": "tests/"
      }
  }
  ```
- **Configuration:** `phpunit.xml.dist` configured with `failOnWarning="true"` and `failOnRisky="true"`.
- **CI Workflow:** GitHub Actions to run tests.
- **Separation:** Fast unit tests will be kept separate from slower integration tests, configured via PHPUnit test suites.
- **Naming Conventions:** Class names must end with `Test`. Methods must start with `test_` or use the `#[Test]` attribute.
- **Directory Structure:**
  ```
  tests/
    Unit/
    Integration/
    Regression/
    Fixtures/
    Support/
  ```

## 2. Unit Test Coverage

Cover all domain-level behavior for the 6 isolated domains.

**A. Commands**
- Self-validation, required fields enforcement.
- Rejection of invalid actor, type, and id values.
- Rejection of invalid metadata or payload structures.
- Timestamp handling (if applicable to command input).
- Immutability / readonly expectations enforcement.

**B. DTOs**
- Proper construction.
- Strict `JsonSerializable` output format.
- Null handling for optional properties.
- Array payload handling.
- Metadata boundaries and defaults.
- Cursor DTO / Query DTO limit enforcements.
- Date fields handling and string formatting.
- Value types mapping.

**C. Policies**
- Default policy behavior per domain.
- Metadata sanitization correctness.
- URL sanitization correctness.
- Sensitive key handling (e.g., hiding passwords/tokens).
- Max metadata size behavior (trimming or rejection).
- Allowed/normalized values.
- Rejection/exception cases for invalid inputs.

**D. Recorders**
- Successful record path.
- Command-based record path.
- Primitive input record path convenience methods.
- Correct DTO formulation and passing to writer/repository.
- Correct usage of the injected Clock.
- Policies application before writing.
- Metadata sanitizer application.
- URL sanitizer application where relevant.

## 3. Failure Semantics Tests

Explicit coverage of failure boundaries for each domain type.

**A. AuthoritativeAudit**
- Fail-closed behavior validation.
- Storage failure must throw a domain-specific exception (e.g., `AuthoritativeAuditStorageException`).
- Invalid command/input must throw an exception.
- No PSR fallback logger behavior (it should ignore any fallback logger).
- Outbox writer failure is NOT swallowed; it must propagate.

**B. Non-authoritative domains (AuditTrail, SecuritySignals, BehaviorTrace, DiagnosticsTelemetry, DeliveryOperations)**
- Fail-open behavior validation.
- Recorder MUST catch `Throwable` at the boundary.
- Fallback logger is called when storage fails.
- Fallback logger failure does not leak back to the caller.
- Repository exceptions are NOT swallowed by the repository itself (only by the recorder).
- Direct repository usage (bypassing recorder) still throws.

## 4. Repository Unit Tests

Using mocked PDO / fake PDO wrappers where reasonable to test logic without DB overhead.

- SQL execution path validation.
- Parameters binding verification.
- Insert payload shape correctness.
- Exception wrapping (translating PDOException to domain exception).
- Query filters logic.
- Cursor clauses construction.
- Ordering clauses application.

*Important:* PDO mocking is useful for testing query building logic, parameter binding, and exception translation. Real MySQL integration tests are better for testing actual schema compliance, JSON functions, and index usage.

## 5. MySQL Integration Tests

Define integration test strategy using a disposable MySQL database.

**Coverage:**
- Schema creation from actual `src/{Domain}/Database/*.sql` files.
- Insert and query roundtrip per domain.
- All six write tables.
- All six query repositories.
- Date range filters (`after`, `before`).
- Actor filters (`actor_type`, `actor_id`).
- Target/entity/subject filters.
- Event/action/signal/status/channel filters.
- RequestId/CorrelationId filters.
- AuthoritativeAudit operations (specifically without requestId filter as per design).
- Cursor pagination DESC behavior.
- Stable ordering with same `occurred_at` and different `id`.
- Limit enforcement.
- Safe corrupt JSON decoding returns null without failing row.
- Storage failures throw domain-specific exceptions.

**Implementation:**
- GitHub Actions MySQL service container.
- Local Docker optional only for running tests locally.
- Environment variables for test DSN (e.g., `DB_DSN`).
- Skip integration tests dynamically if DB env is missing, OR separate them into a distinct CI job.

## 6. Regression Tests

Must protect architecture boundaries against future changes:

- Ensure no `GenericLogger` class exists.
- Ensure no `GenericRecorder` class exists.
- Ensure no `GenericLogDTO` class exists.
- Ensure no `GenericReader` class exists.
- Ensure no `GenericQueryRepository` class exists.
- Ensure no generic log table exists.
- Ensure no `App` namespace is used or referenced.
- Ensure no framework bindings (Slim, Laravel, Symfony, PHP-DI) are present in `src/`.
- Ensure no routes/controllers/middleware/admin UI are introduced.
- Ensure no host app dependencies in `composer.json`.
- Provider has typed accessors only (e.g., `authoritativeAudit()`).
- Provider has no generic log/record/dispatch/route method.
- `AuthoritativeAudit` factory does not accept PSR logger.
- Non-authoritative factories may accept optional PSR logger.
- `BehaviorTrace` and `DiagnosticsTelemetry` legacy read methods remain for backward compatibility.

## 7. Static Analysis / Quality Gates

Required CI gates:
- `composer validate`
- PHP lint (`find src -name "*.php" -exec php -l {} \;`)
- PHPStan max (`vendor/bin/phpstan analyse -c phpstan.neon`)
- PHPUnit unit tests (`vendor/bin/phpunit --testsuite Unit`)
- PHPUnit integration tests (if DB available, `--testsuite Integration`)
- Infection/mutation testing as optional future hardening.
- Test coverage report as optional target.

**Coverage goals:**
- Required critical path coverage (Recorders, Providers, Repositories).
- Recommended minimum line/branch coverage targets (e.g., 85%).
- Mutation score target if introduced later.

## 8. Security / Safety Tests

- Secrets are not stored in metadata.
- Tokens/passwords/OTP/session values are sanitized/masked.
- URL query strings are removed.
- Secret-looking path segments are masked.
- Corrupt JSON does not crash reads (returns null).
- Oversized metadata handling.
- No recursive fallback logging loops.

## 9. Backward Compatibility Tests

- Public API constructors/signatures remain intact.
- Legacy `read()` methods for `BehaviorTrace` and `DiagnosticsTelemetry` remain.
- Factory method names are consistent.
- Provider typed accessor names remain standard.
- Expected exception classes are thrown as documented.
- Package namespace `Maatify\EventLogging\` remains stable.

## 10. Examples Coverage Plan

A complete examples plan is documented in [EXAMPLES_COVERAGE_PLAN.md](../examples/EXAMPLES_COVERAGE_PLAN.md).

## 11. Examples Validation

How examples should be validated:
- Syntax check examples (`php -l examples/*.php`).
- Static analysis on examples if possible (add `examples` to `phpstan.neon` paths).
- README links to examples are valid.
- Examples must not contradict integration docs.
- Examples must not introduce framework assumptions.
- Examples must not include real DB credentials.

## 12. Implementation Phases

**Phase A — Test Infrastructure**
- **Goal:** Setup PHPUnit, configurations, and CI workflows.
- **Files:** `phpunit.xml.dist`, `tests/bootstrap.php`, `.github/workflows/test.yml`.
- **Validation:** `composer validate`, `vendor/bin/phpunit` runs empty.
- **Out of scope:** Writing actual tests.
- **Commit:** `build(tests): setup phpunit infrastructure`

**Phase B — Unit Tests for DTOs/Commands/Policies**
- **Goal:** Cover value objects, DTOs, and policies.
- **Files:** `tests/Unit/Domain/*/CommandTest.php`, `tests/Unit/Domain/*/DTOTest.php`, `tests/Unit/Domain/*/PolicyTest.php`.
- **Validation:** Unit tests pass.
- **Out of scope:** Recorders and Repositories.
- **Commit:** `test(domain): add unit tests for commands, dtos, and policies`

**Phase C — Recorder Behavior Tests**
- **Goal:** Cover recording paths and failure semantics.
- **Files:** `tests/Unit/Domain/*/RecorderTest.php`.
- **Validation:** Unit tests pass.
- **Out of scope:** Database writes.
- **Commit:** `test(domain): add recorder behavior and failure semantics tests`

**Phase D — Repository Unit Tests**
- **Goal:** Cover repository logic using mocked PDO.
- **Files:** `tests/Unit/Domain/*/RepositoryTest.php`.
- **Validation:** Unit tests pass.
- **Out of scope:** Real MySQL DB.
- **Commit:** `test(repository): add repository unit tests with mocked PDO`

**Phase E — MySQL Integration Tests**
- **Goal:** End-to-end repository tests against real MySQL.
- **Files:** `tests/Integration/Domain/*/MysqlRepositoryTest.php`.
- **Validation:** Integration tests pass against DB.
- **Out of scope:** Application-level integration.
- **Commit:** `test(integration): add mysql integration tests`

**Phase F — Architecture Regression Tests**
- **Goal:** Protect boundaries and package rules.
- **Files:** `tests/Regression/ArchitectureTest.php`.
- **Validation:** Regression tests pass.
- **Out of scope:** Business logic testing.
- **Commit:** `test(regression): add architecture boundary protection tests`

**Phase G — Examples Skeleton**
- **Goal:** Create base examples.
- **Files:** `examples/*.php` as defined in coverage plan.
- **Validation:** Syntax checks pass.
- **Out of scope:** Implementation details.
- **Commit:** `docs(examples): add example script skeletons`

**Phase H — Examples Validation & Docs Index**
- **Goal:** Ensure examples are valid and linked.
- **Files:** Update README.md, `phpstan.neon`.
- **Validation:** `php -l examples/*.php`, PHPStan passes.
- **Out of scope:** Modifying core package code.
- **Commit:** `docs(examples): validate examples and update documentation`

**Phase I — Final Testing Hardening Audit**
- **Goal:** Final review of coverage.
- **Files:** `docs/audits/TESTING_HARDENING_AUDIT.md`.
- **Validation:** All tests, CI pass.
- **Out of scope:** Feature additions.
- **Commit:** `docs(audit): finalize testing hardening audit`

## 13. Final Hardening Gate

Gate before v1.0 stable release:
- All unit tests pass.
- Integration tests pass (or are documented as optional CI job).
- Examples syntax pass.
- PHPStan max pass.
- No architecture regression.
- Docs updated.
- No public API drift.
- Final audit doc created.

*Important architecture rules maintained throughout:*
- Do not propose adding framework-specific examples.
- Do not propose adding generic logger/reader abstractions.
- Do not propose changing package behavior just to test it.
- Do not propose adding host app code.
- Do not propose adding admin UI.
- Keep all tests and examples package-level and framework-agnostic.
- Mark MySQL integration tests as package integration tests, not host integration tests.

---

## Required Recommendation

The testing and examples hardening described in this roadmap are:
- **NOT required** for current Integration Release Readiness (the package is already ready for integration).
- **Recommended** before v1.0 stable.
- **Required** before public stable release.
