# Testing Strategy

Recommended validation for `maatify/event-logging`:

1. Run `composer validate` inside `packages/event-logging`.
2. Run PHP syntax checks over every copied PHP file.
3. Add domain-level unit tests for recorder behavior, policy enforcement, DTO boundaries, and storage-contract calls.
4. Add integration tests for MySQL repositories using disposable databases and the copied schema files.
5. Add regression tests proving domains stay isolated and no generic logger/DTO/recorder/table is introduced.
6. Add static analysis after package dependencies and CI are finalized.

This extraction phase does not wire the package into Athar Admin runtime behavior.
