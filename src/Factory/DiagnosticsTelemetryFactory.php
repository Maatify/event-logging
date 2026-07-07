<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Factory;

use Maatify\EventLogging\Common\ClockInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryLoggerInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryLoggerMysqlRepository;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryRecorder;
use PDO;
use Psr\Log\LoggerInterface;

final class DiagnosticsTelemetryFactory
{
    public static function create(
        PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null,
        ?DiagnosticsTelemetryPolicyInterface $policy = null
    ): DiagnosticsTelemetryRecorder {
        return new DiagnosticsTelemetryRecorder(self::createLogger($pdo), $clock, $psrLogger, $policy);
    }

    public static function createLogger(PDO $pdo): DiagnosticsTelemetryLoggerInterface
    {
        return new DiagnosticsTelemetryLoggerMysqlRepository($pdo);
    }
}
