<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';
example_requires_pdo($pdo);

use Maatify\EventLogging\AuditTrail\Contract\AuditTrailPolicyInterface;
use Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailLoggerMysqlRepository;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;

/**
 * 12 - Custom Policy
 *
 * Show how to create and inject a custom policy.
 */

// We assume $pdo and $clock are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\EventLogging\Common\ClockInterface $clock

class MyCustomAuditTrailPolicy implements AuditTrailPolicyInterface
{
    public function normalizeActorType(string|AuditTrailActorTypeEnum $actorType): string
    {
        // Custom logic: always return uppercase string
        $val = $actorType instanceof AuditTrailActorTypeEnum ? $actorType->value : $actorType;
        return strtoupper(trim($val));
    }

    public function validateMetadataSize(string $json): bool
    {
        // Custom logic: very strict 1KB limit
        return strlen($json) <= 1024;
    }
}

$repository = new AuditTrailLoggerMysqlRepository($pdo);
$customPolicy = new MyCustomAuditTrailPolicy();

$recorder = new AuditTrailRecorder(
    logger: $repository,
    clock: $clock,
    fallbackLogger: null,
    policy: $customPolicy
);

echo "Instantiated AuditTrailRecorder with custom policy.\n";
