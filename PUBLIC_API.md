# Public API

The public entry points are the domain-specific contracts, DTOs, enums, recorders, policies, exceptions, and storage repositories under these namespaces:

- `Maatify\EventLogging\AuthoritativeAudit\Command\*`
- `Maatify\EventLogging\AuthoritativeAudit\Contract\*`
- `Maatify\EventLogging\AuthoritativeAudit\DTO\*`
- `Maatify\EventLogging\AuthoritativeAudit\Enum\*`
- `Maatify\EventLogging\AuthoritativeAudit\Exception\*`
- `Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\*`
- `Maatify\EventLogging\AuthoritativeAudit\Recorder\*`
- `Maatify\EventLogging\AuditTrail\Command\*`
- `Maatify\EventLogging\AuditTrail\Contract\*`
- `Maatify\EventLogging\AuditTrail\DTO\*`
- `Maatify\EventLogging\AuditTrail\Enum\*`
- `Maatify\EventLogging\AuditTrail\Exception\*`
- `Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\*`
- `Maatify\EventLogging\AuditTrail\Recorder\*`
- `Maatify\EventLogging\SecuritySignals\Command\*`
- `Maatify\EventLogging\SecuritySignals\Contract\*`
- `Maatify\EventLogging\SecuritySignals\DTO\*`
- `Maatify\EventLogging\SecuritySignals\Enum\*`
- `Maatify\EventLogging\SecuritySignals\Exception\*`
- `Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\*`
- `Maatify\EventLogging\SecuritySignals\Recorder\*`
- `Maatify\EventLogging\BehaviorTrace\Command\*`
- `Maatify\EventLogging\BehaviorTrace\Contract\*`
- `Maatify\EventLogging\BehaviorTrace\DTO\*`
- `Maatify\EventLogging\BehaviorTrace\Enum\*`
- `Maatify\EventLogging\BehaviorTrace\Exception\*`
- `Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\*`
- `Maatify\EventLogging\BehaviorTrace\Recorder\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Command\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Contract\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\DTO\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Enum\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Exception\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\*`
- `Maatify\EventLogging\DiagnosticsTelemetry\Recorder\*`
- `Maatify\EventLogging\DeliveryOperations\Command\*`
- `Maatify\EventLogging\DeliveryOperations\Contract\*`
- `Maatify\EventLogging\DeliveryOperations\DTO\*`
- `Maatify\EventLogging\DeliveryOperations\Enum\*`
- `Maatify\EventLogging\DeliveryOperations\Exception\*`
- `Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\*`
- `Maatify\EventLogging\DeliveryOperations\Recorder\*`
- `Maatify\EventLogging\Common\ClockInterface`
- `Maatify\EventLogging\Common\SystemClock`
- `Maatify\EventLogging\Common\UrlSanitizer`
- `Maatify\EventLogging\Common\MetadataSanitizer`
- `Maatify\EventLogging\Factory\AuthoritativeAuditFactory`
- `Maatify\EventLogging\Factory\AuditTrailFactory`
- `Maatify\EventLogging\Factory\SecuritySignalsFactory`
- `Maatify\EventLogging\Factory\BehaviorTraceFactory`
- `Maatify\EventLogging\Factory\DiagnosticsTelemetryFactory`
- `Maatify\EventLogging\Factory\DeliveryOperationsFactory`
- `Maatify\EventLogging\Provider\EventLoggingProvider`
- `Maatify\EventLogging\Provider\EventLoggingProviderFactory`

No `App\`, project DI/container, project helper, or host-application-specific configuration API is part of the exported package surface.
