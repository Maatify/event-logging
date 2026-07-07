<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Factory;

use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditOutboxWriterInterface;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditPolicyInterface;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditOutboxWriterMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
use Maatify\EventLogging\Common\ClockInterface;
use PDO;

final class AuthoritativeAuditFactory
{
    public static function create(
        PDO $pdo,
        ClockInterface $clock,
        ?AuthoritativeAuditPolicyInterface $policy = null
    ): AuthoritativeAuditRecorder {
        return new AuthoritativeAuditRecorder(
            self::createWriter($pdo),
            $clock,
            $policy
        );
    }

    public static function createWriter(PDO $pdo): AuthoritativeAuditOutboxWriterInterface
    {
        return new AuthoritativeAuditOutboxWriterMysqlRepository($pdo);
    }
}
