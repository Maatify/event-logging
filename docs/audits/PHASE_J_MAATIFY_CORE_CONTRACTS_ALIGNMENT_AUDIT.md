# Phase J — Maatify Core Contracts Alignment Audit

## Scope
Review the compliance of the `maatify/event-logging` library with Maatify core contracts (`maatify/exceptions` and `maatify/shared-common`), specifically focusing on Exception inheritance and the Clock interface. This phase serves as an audit and design step prior to implementation.

## Current Main Head
3088293204d2d9de7619adf9666d5c36a40c0eca

## Decision Background
The Maatify ecosystem has established shared standards for exception handling and common contracts (such as the Clock interface). To ensure `maatify/event-logging` fits smoothly into the broader ecosystem while remaining a standalone module (i.e. not tightly coupled to a host application like Slim, Laravel, etc.), it must adopt `maatify/exceptions` and `maatify/shared-common` without breaking backward compatibility unnecessarily or introducing generic logging abstractions.

## Exceptions Inventory

The library currently defines 6 exceptions, all of which directly extend `RuntimeException`:
1. `Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException`
2. `Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException`
3. `Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException`
4. `Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException`
5. `Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException`
6. `Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException`

**Mapping Proposal**:
These are primarily storage-related errors wrapping PDO exceptions. The most appropriate base class in `maatify/exceptions` is `Maatify\Exceptions\Exception\System\DatabaseConnectionMaatifyException` (or `SystemMaatifyException`).
**Recommendation**: Make these domain exceptions extend `DatabaseConnectionMaatifyException` (or `SystemMaatifyException`) from `maatify/exceptions`. The domain-specific exception names MUST be kept to prevent breaking `catch` blocks in host applications.

## Clock Inventory

**Current State**:
- Interface: `Maatify\EventLogging\Common\ClockInterface` (`now(): DateTimeImmutable`)
- Implementation: `Maatify\EventLogging\Common\SystemClock`
- Used extensively in:
  - All Domains Recorders (`BehaviorTraceRecorder`, `AuditTrailRecorder`, etc.)
  - `EventLoggingProviderFactory` and Domain Factories (`BehaviorTraceFactory`, etc.)
  - Examples (`examples/*.php`)
  - Testing support (`tests/Support/FixedClock.php`)
  - Integration documentation (`docs/integration/FACTORY_USAGE.md`)

**Target State**:
- Contract: `Maatify\SharedCommon\Contracts\ClockInterface` (`now(): DateTimeImmutable`, `getTimezone(): DateTimeZone`)

**Required Migration**:
All typed references to `Maatify\EventLogging\Common\ClockInterface` must be updated to `Maatify\SharedCommon\Contracts\ClockInterface`. Additionally, any concrete implementations (`SystemClock`, `FixedClock`) must implement the new `getTimezone(): DateTimeZone` method to satisfy the new interface.

## Composer Dependency Review

Adding `maatify/exceptions` and `maatify/shared-common`:
- `maatify/exceptions` (v1.1.0) and `maatify/shared-common` (v1.0.2) both support PHP `^8.2`, which aligns perfectly with `maatify/event-logging`'s `^8.2` requirement.
- Adding these does **not** violate the standalone package concept because these packages only contain interfaces, enums, exceptions, and foundational policies (they have no framework-specific bindings).

## Public API Impact

- **Exceptions**: Changing the inheritance of exceptions from `RuntimeException` to `MaatifyException` -> `RuntimeException` will NOT break existing `catch (RuntimeException $e)` blocks.
- **ClockInterface**: Replacing the internal `ClockInterface` with `Maatify\SharedCommon\Contracts\ClockInterface` will break backward compatibility for host applications explicitly passing their own implementations or using the current `Maatify\EventLogging\Common\ClockInterface` in their type hints.
  - This change will alter constructor signatures in all recorders and factories.
  - Existing implementations of `ClockInterface` by host applications will be broken unless they add `getTimezone(): DateTimeZone`.

## Backward Compatibility Assessment
The API breakage in the `ClockInterface` namespaces and signatures is inevitable for true ecosystem alignment.
- **Bridge Strategy**: Creating an alias or a bridge interface (e.g., keeping the old interface and making it extend the new one) could delay breakage but creates unnecessary duplication and violates the single-source-of-truth directive for the Clock contract.
- **Release Strategy**: Given the `v1.0` tag is not finalized, it is highly recommended to release this change as a major version bump or RC to explicitly denote breaking changes to the `ClockInterface` contract and namespace.

## Proposed Implementation Plan

**For Codex (Implementation Phase):**
1. **Composer**: Update `composer.json` to require `"maatify/exceptions": "^1.1"`, `"maatify/shared-common": "^1.0"`.
2. **Clock Migration**:
   - Replace `use Maatify\EventLogging\Common\ClockInterface;` with `use Maatify\SharedCommon\Contracts\ClockInterface;` across `src/`, `tests/`, `examples/`, and documentation.
   - Delete `src/Common/ClockInterface.php`.
   - Update `SystemClock` and `FixedClock` to implement `getTimezone(): DateTimeZone`.
3. **Exception Migration**:
   - Update the 6 domain storage exceptions to extend `Maatify\Exceptions\Exception\System\DatabaseConnectionMaatifyException` (or `SystemMaatifyException`).
   - Remove `use RuntimeException;` and implement any required abstract methods (or rely on the base class implementations).
4. **Testing**:
   - Update `tests/Regression/ArchitectureTest.php` if it currently asserts for internal primitives/ClockInterface.
   - Verify `phpunit` passes with the newly mocked/real clocks.
5. **Docs**: Update all references in the documentation to the new `ClockInterface` FQCN.

## Blockers
None. The packages exist, versions align, and the path to refactor is clear.

## Non-blockers
- Backward compatibility breakage of `ClockInterface`: This is accepted given the broader ecosystem alignment goals before the final stable release.

## Final Verdict
**NEEDS IMPLEMENTATION** (Audit confirms required changes)

## Validation Results
As part of the audit, the current main head was validated to ensure base stability before starting Phase J:
- `composer validate`: PASS (`./composer.json is valid`)
- `php -l src/ tests/ examples/`: PASS (No syntax errors detected)
- `vendor/bin/phpstan analyse -c phpstan.neon`: PASS
- `vendor/bin/phpunit`: PASS (All tests passed, no errors)

---

## Codex Prompt

```text
Please implement Phase J - Maatify Core Contracts Alignment based on the audit report.

1. Add `maatify/exceptions: ^1.1` and `maatify/shared-common: ^1.0` to `composer.json` requirements.
2. Delete `src/Common/ClockInterface.php`.
3. Find all usages of `Maatify\EventLogging\Common\ClockInterface` in `src/`, `tests/`, `examples/`, and `docs/` and replace them with `Maatify\SharedCommon\Contracts\ClockInterface`.
4. Update `SystemClock` (`src/Common/SystemClock.php`) and `FixedClock` (`tests/Support/FixedClock.php`) to implement the `getTimezone(): DateTimeZone` method required by the new interface.
5. Update all 6 storage exception classes in `src/` to extend `Maatify\Exceptions\Exception\System\DatabaseConnectionMaatifyException` instead of `RuntimeException`. Ensure no specific domain names are changed.
6. Verify architecture tests in `tests/Regression/ArchitectureTest.php` pass, specifically those checking the `Common` module primitives, adjusting assertions as needed since `ClockInterface.php` is removed.
7. Run tests, PHPStan, and examples validation to verify success.
```
