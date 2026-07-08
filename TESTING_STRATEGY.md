# Testing Strategy

Recommended validation for `maatify/event-logging`:

1. Run `composer validate` inside `packages/event-logging`.
2. Run PHP syntax checks over every copied PHP file.
3. Add domain-level unit tests for recorder behavior, policy enforcement, DTO boundaries, and storage-contract calls.
4. Add integration tests for MySQL repositories using disposable databases and the copied schema files.
5. Add regression tests proving domains stay isolated and no generic logger/DTO/recorder/table is introduced.
6. Add static analysis after package dependencies and CI are finalized.

## Running Tests

To run the local test suite, ensure dependencies are installed and use PHPUnit:

```bash
composer install
composer test
composer test:unit
vendor/bin/phpunit
```

### Integration Tests

The integration test suite specifically tests MySQL repository round-trips.

To run the integration tests, you must provide a valid MySQL DSN using environment variables.
If the `EVENT_LOGGING_TEST_MYSQL_DSN` environment variable is not present, the integration tests will be safely skipped.

```bash
EVENT_LOGGING_TEST_MYSQL_DSN="mysql:host=127.0.0.1;port=3306;dbname=test_db" \
EVENT_LOGGING_TEST_MYSQL_USER="test_user" \
EVENT_LOGGING_TEST_MYSQL_PASSWORD="test_pass" \
vendor/bin/phpunit --testsuite Integration
```

This phase does not wire the package into host application runtime behavior.
