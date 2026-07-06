# Public API Definition

## Recorder
`Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder`

- `record(...)`: Primary entry point. Returns `void`. Never throws.

## Contracts
`Maatify\EventLogging\AuditTrail\Contract\AuditTrailLoggerInterface`
- `write(AuditTrailRecordDTO $record): void`

`Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface`
- `find(AuditTrailQueryDTO $query): array<AuditTrailViewDTO>`

`Maatify\EventLogging\AuditTrail\Contract\AuditTrailPolicyInterface`
- `normalizeActorType(...)`
- `validateMetadataSize(...)`

## DTOs
`Maatify\EventLogging\AuditTrail\DTO\AuditTrailRecordDTO` (Immutable, Read-only)
`Maatify\EventLogging\AuditTrail\DTO\AuditTrailViewDTO` (Immutable, Read-only)
`Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO` (Read-only properties)

## Enum
`Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum`
- SYSTEM, ADMIN, USER, SERVICE, API_CLIENT, ANONYMOUS
