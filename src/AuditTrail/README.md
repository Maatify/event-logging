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

### Admin Query Pagination

For host-owned admin tables that need offset pagination, instantiate the separate Admin Query repository:

```php
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailAdminQueryMysqlRepository;

$adminQuery = new AuditTrailAdminQueryMysqlRepository($pdo);

$page = $adminQuery->paginate(new AuditTrailAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    entityType: 'customer',
    entityId: 456,
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC'
));
```

The Admin Query API supports actor, event key, entity, subject, request, correlation, and inclusive date-range filters. Type-only filters are valid; ID-only filters are invalid. The response contains `items`, `page`, `perPage`, `total`, `filtered`, `totalPages`, `hasNext`, `hasPrevious`, `sortBy`, and `sortDirection`.

The primitive `AuditTrailQueryInterface` remains available and unchanged. The removed AuditTrail cursor wrapper artifacts were unreleased post-v1 experiments, not v1 contracts.

This package does not provide HTTP controllers, authorization, routes, UI, exports, localization, free-text search, metadata search, or dashboards.

## Configuration

Ensure `AuditTrailRecorder` is wired in your DI container with:
- `AuditTrailLoggerInterface` implementation (e.g. `AuditTrailLoggerMysqlRepository`)
- `Maatify\SharedCommon\Contracts\ClockInterface` implementation
