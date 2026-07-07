# Manual Wiring

The `maatify/event-logging` library is completely container and framework agnostic. While you can use the provided factories, you might prefer or need to manually wire the dependencies yourself (e.g. to inject custom policies or when adhering to specific DI paradigms). This document outlines how to manually wire the components of a logging domain.

## Preserving Domain Isolation

A core architectural principle of this library is the strict isolation of domains. There are no generic "loggers", "recorders", "repositories", or "DTOs". When manually wiring, you must instantiate the specific classes for each domain you intend to use.

## No Internal Framework Bindings

The package contains no bindings or auto-discovery configuration for frameworks such as Laravel, Symfony, Slim, or specific containers like PHP-DI. The host application's container is entirely responsible for discovering, instantiating, and managing the lifecycle of these objects externally.

## Manually Constructing Domain Recorders and Repositories

Wiring a domain typically involves constructing an infrastructure repository (the writer), and then injecting that into the domain recorder along with a clock and an optional fallback logger.

### Example: Wiring the Audit Trail Domain

```php
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
use Maatify\EventLogging\Common\SystemClock;

// 1. Host provides dependencies
$pdo = /* ... PDO instance ... */;
$clock = new SystemClock();
$psrLogger = /* ... PSR-3 Logger Interface ... */;

// 2. Construct the specific repository/writer
$auditTrailRepository = new AuditTrailLoggerMysqlRepository($pdo);

// 3. Construct the recorder, injecting the repository, clock, and PSR logger
$auditTrailRecorder = new AuditTrailRecorder(
    logger: $auditTrailRepository,
    clock: $clock,
    fallbackLogger: $psrLogger, // Optional fail-open behavior
    policy: null
);
```

### Injecting Custom Policies

Some domains use policies to determine specific behavior, which you can customize when manually wiring. For instance, the `AuthoritativeAudit` domain uses a policy to filter sensitive data from payloads or enforce specific actor types.

```php
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditPolicyInterface;
use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditActorTypeInterface;
use Maatify\EventLogging\Common\SystemClock;

// 1. Host provides dependencies
$pdo = /* ... PDO instance ... */;
$clock = new SystemClock();

// 2. Create a custom policy implementing the domain's policy contract
class MyCustomAuditPolicy implements AuthoritativeAuditPolicyInterface {
    public function validatePayload(array $payload): bool {
        // Custom validation logic here
        return !isset($payload['credit_card']);
    }

    public function normalizeActorType(AuthoritativeAuditActorTypeInterface|string $actorType): string {
        return is_string($actorType) ? $actorType : $actorType->value();
    }
}

$customPolicy = new MyCustomAuditPolicy();

// 3. Construct the repository
$authoritativeAuditRepository = new AuthoritativeAuditOutboxWriterMysqlRepository($pdo);

// 4. Construct the recorder, injecting the custom policy
$authoritativeAuditRecorder = new AuthoritativeAuditRecorder(
    writer: $authoritativeAuditRepository,
    clock: $clock,
    policy: $customPolicy
    // Note: No PSR logger provided here as this domain is fail-closed
);
```

By manually wiring, your application retains complete control over the configuration and behavior of the logging domains while keeping the core package strictly focused on logging operations.
