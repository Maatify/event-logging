<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Factory;

use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsLoggerInterface;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsPolicyInterface;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsLoggerMysqlRepository;
use Maatify\EventLogging\DeliveryOperations\Recorder\DeliveryOperationsRecorder;
use PDO;
use Psr\Log\LoggerInterface;

final class DeliveryOperationsFactory
{
    public static function create(
        PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null,
        ?DeliveryOperationsPolicyInterface $policy = null
    ): DeliveryOperationsRecorder {
        return new DeliveryOperationsRecorder(self::createLogger($pdo), $clock, $psrLogger, $policy);
    }

    public static function createLogger(PDO $pdo): DeliveryOperationsLoggerInterface
    {
        return new DeliveryOperationsLoggerMysqlRepository($pdo);
    }
}
