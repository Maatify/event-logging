<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';

use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceWriterMysqlRepository;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceRecorder;

/**
 * 02 - Manual Wiring
 *
 * Demonstrates how to wire a single domain manually without using the factory.
 */

// We assume $pdo, $clock, and $logger are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\EventLogging\Common\ClockInterface $clock
// @var \Psr\Log\LoggerInterface $logger

$writer = new BehaviorTraceWriterMysqlRepository($pdo);
$recorder = new BehaviorTraceRecorder(
    writer: $writer,
    clock: $clock,
    fallbackLogger: $logger,
    policy: null // The recorder will instantiate the default policy internally if null is provided
);

echo "Manually wired BehaviorTraceRecorder successfully.\n";
