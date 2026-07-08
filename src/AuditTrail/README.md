# AuditTrail Module

A framework-agnostic standalone library (uses explicit Composer/runtime dependencies) for logging data access, views, and exports.

## Installation

This module is part of the `Maatify` logging system.
Namespace: `Maatify\EventLogging\AuditTrail\`

## Usage

### Recording an Event

Inject `Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder` and call `record()`.

```php
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
use Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum;

class CustomerController {
    public function __construct(
        private AuditTrailRecorder $auditRecorder
    ) {}

    public function show(int $id) {
        // ... business logic ...

        $this->auditRecorder->record(
            eventKey: 'customer.view',
            actorType: AuditTrailActorTypeEnum::ADMIN,
            actorId: $currentUserId,
            entityType: 'customer',
            entityId: $id,
            metadata: ['section' => 'billing']
        );
    }
}
```

### Querying Events

Inject `Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface`.

```php
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;

$query = new AuditTrailQueryDTO(
    actorId: 123,
    limit: 10
);

$logs = $queryRepo->find($query);
```

## Configuration

Ensure `AuditTrailRecorder` is wired in your DI container with:
- `AuditTrailLoggerInterface` implementation (e.g. `AuditTrailLoggerMysqlRepository`)
- `Maatify\SharedCommon\Contracts\ClockInterface` implementation
