# SecuritySignals Module

A framework-agnostic standalone library (uses explicit Composer/runtime dependencies) for logging security indicators and alerts.

## Installation

This module is part of the `Maatify` logging system.
Namespace: `Maatify\EventLogging\SecuritySignals\`

## Usage

### Recording a Signal

Inject `Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder` and call `record()`.

```php
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum;

class AuthController {
    public function __construct(
        private SecuritySignalsRecorder $signalsRecorder
    ) {}

    public function login() {
        // ... failed login ...

        $this->signalsRecorder->record(
            signalType: 'login_failed',
            severity: SecuritySignalSeverityEnum::WARNING,
            actorType: SecuritySignalActorTypeEnum::ANONYMOUS,
            actorId: null,
            metadata: ['reason' => 'bad_password']
        );
    }
}
```

## Configuration

Ensure `SecuritySignalsRecorder` is wired in your DI container with:
- `SecuritySignalsLoggerInterface` implementation (e.g. `SecuritySignalsLoggerMysqlRepository`)
- `Maatify\SharedCommon\Contracts\ClockInterface` implementation

## Admin Query

For host-owned administrative screens that need deterministic offset pagination, use the separate Admin Query API:

```php
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsAdminQueryMysqlRepository;

$query = new SecuritySignalsAdminQueryMysqlRepository($pdo);

$page = $query->paginate(new SecuritySignalsAdminQueryRequestDTO(
    actorType: 'admin',
    actorId: 123,
    signalType: 'login_failed',
    severity: 'HIGH',
    page: 1,
    perPage: 20,
    sortBy: 'occurred_at',
    sortDirection: 'DESC',
));
```

Supported filters are `actorType`, `actorId`, `signalType`, `severity`, `requestId`, `correlationId`, `after`, and `before`. `actorType` and `actorId` are independent filters. Pagination mechanics are delegated to `maatify/persistence`; the public EventLogging API exposes only package-owned contracts and DTOs.
