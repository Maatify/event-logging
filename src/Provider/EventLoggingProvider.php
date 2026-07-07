<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Provider;

use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceRecorder;
use Maatify\EventLogging\DeliveryOperations\Recorder\DeliveryOperationsRecorder;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryRecorder;
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder;

final class EventLoggingProvider
{
    public function __construct(
        private readonly AuthoritativeAuditRecorder $authoritativeAudit,
        private readonly AuditTrailRecorder $auditTrail,
        private readonly SecuritySignalsRecorder $securitySignals,
        private readonly BehaviorTraceRecorder $behaviorTrace,
        private readonly DiagnosticsTelemetryRecorder $diagnosticsTelemetry,
        private readonly DeliveryOperationsRecorder $deliveryOperations
    ) {
    }

    public function authoritativeAudit(): AuthoritativeAuditRecorder
    {
        return $this->authoritativeAudit;
    }

    public function auditTrail(): AuditTrailRecorder
    {
        return $this->auditTrail;
    }

    public function securitySignals(): SecuritySignalsRecorder
    {
        return $this->securitySignals;
    }

    public function behaviorTrace(): BehaviorTraceRecorder
    {
        return $this->behaviorTrace;
    }

    public function diagnosticsTelemetry(): DiagnosticsTelemetryRecorder
    {
        return $this->diagnosticsTelemetry;
    }

    public function deliveryOperations(): DeliveryOperationsRecorder
    {
        return $this->deliveryOperations;
    }
}
