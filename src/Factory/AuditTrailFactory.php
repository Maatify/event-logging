<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Factory;

use Maatify\EventLogging\AuditTrail\Contract\AuditTrailLoggerInterface;
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailPolicyInterface;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
use Maatify\EventLogging\Common\ClockInterface;
use PDO;
use Psr\Log\LoggerInterface;

final class AuditTrailFactory
{
    public static function create(
        PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null,
        ?AuditTrailPolicyInterface $policy = null
    ): AuditTrailRecorder {
        return new AuditTrailRecorder(self::createLogger($pdo), $clock, $psrLogger, $policy);
    }

    public static function createLogger(PDO $pdo): AuditTrailLoggerInterface
    {
        return new AuditTrailLoggerMysqlRepository($pdo);
    }
}
