# Public API Definition

## Recorder
`Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder`

- `record(...)`: Primary entry point. Returns `void`. Never throws.

## Contracts
`Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsLoggerInterface`
- `write(SecuritySignalRecordDTO $record): void`

`Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsPolicyInterface`
- `normalizeActorType(...)`
- `normalizeSeverity(...)`
- `validateMetadataSize(...)`

## DTOs
`Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalRecordDTO` (Immutable, Read-only)

## Enum
`Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum`
- SYSTEM, ADMIN, USER, SERVICE, API_CLIENT, ANONYMOUS

`Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum`
- INFO, WARNING, ERROR, CRITICAL
