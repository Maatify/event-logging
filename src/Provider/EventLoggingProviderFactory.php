<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Provider;

use Maatify\EventLogging\Common\ClockInterface;
use Maatify\EventLogging\Factory\AuditTrailFactory;
use Maatify\EventLogging\Factory\AuthoritativeAuditFactory;
use Maatify\EventLogging\Factory\BehaviorTraceFactory;
use Maatify\EventLogging\Factory\DeliveryOperationsFactory;
use Maatify\EventLogging\Factory\DiagnosticsTelemetryFactory;
use Maatify\EventLogging\Factory\SecuritySignalsFactory;
use PDO;
use Psr\Log\LoggerInterface;

final class EventLoggingProviderFactory
{
    public static function createDefault(
        PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null
    ): EventLoggingProvider {
        return new EventLoggingProvider(
            AuthoritativeAuditFactory::create($pdo, $clock),
            AuditTrailFactory::create($pdo, $clock, $psrLogger),
            SecuritySignalsFactory::create($pdo, $clock, $psrLogger),
            BehaviorTraceFactory::create($pdo, $clock, $psrLogger),
            DiagnosticsTelemetryFactory::create($pdo, $clock, $psrLogger),
            DeliveryOperationsFactory::create($pdo, $clock, $psrLogger)
        );
    }
}
