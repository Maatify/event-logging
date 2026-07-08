<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';

use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\BehaviorTrace\Command\RecordBehaviorTraceCommand;

/**
 * 06 - Behavior Trace Record
 *
 * Show how to record a behavior trace.
 */

// We assume $pdo, $clock, and $logger are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\EventLogging\Common\ClockInterface $clock
// @var \Psr\Log\LoggerInterface $logger

$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $logger);
$behaviorTrace = $provider->behaviorTrace();

$command = new RecordBehaviorTraceCommand(
    action: 'click_button',
    actorType: 'user',
    actorId: 42,
    entityType: 'button',
    entityId: 1,
    metadata: ['button_color' => 'blue']
);

$behaviorTrace->recordCommand($command);

echo "Recorded behavior trace event.\n";
