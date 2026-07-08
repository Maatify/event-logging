<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Factory;

use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsLoggerInterface;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsPolicyInterface;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsLoggerMysqlRepository;
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder;
use PDO;
use Psr\Log\LoggerInterface;

final class SecuritySignalsFactory
{
    public static function create(
        PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null,
        ?SecuritySignalsPolicyInterface $policy = null
    ): SecuritySignalsRecorder {
        return new SecuritySignalsRecorder(self::createLogger($pdo), $clock, $psrLogger, $policy);
    }

    public static function createLogger(PDO $pdo): SecuritySignalsLoggerInterface
    {
        return new SecuritySignalsLoggerMysqlRepository($pdo);
    }
}
