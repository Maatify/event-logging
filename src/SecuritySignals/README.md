# SecuritySignals Module

A standalone library for logging security indicators and alerts.

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
- `ClockInterface` implementation
