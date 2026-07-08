<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Factory;

use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePolicyInterface;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceWriterInterface;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceWriterMysqlRepository;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceRecorder;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;
use Psr\Log\LoggerInterface;

final class BehaviorTraceFactory
{
    public static function create(
        PDO $pdo,
        ClockInterface $clock,
        ?LoggerInterface $psrLogger = null,
        ?BehaviorTracePolicyInterface $policy = null
    ): BehaviorTraceRecorder {
        return new BehaviorTraceRecorder(self::createWriter($pdo), $clock, $psrLogger, $policy);
    }

    public static function createWriter(PDO $pdo): BehaviorTraceWriterInterface
    {
        return new BehaviorTraceWriterMysqlRepository($pdo);
    }
}
