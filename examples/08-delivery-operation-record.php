<?php

declare(strict_types=1);

require_once __DIR__ . '/00-bootstrap.php';

use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\DeliveryOperations\Command\RecordDeliveryOperationCommand;

/**
 * 08 - Delivery Operation Record
 *
 * Show how to record delivery lifecycle events.
 */

// We assume $pdo, $clock, and $logger are available from 00-bootstrap.php.
// @var \PDO $pdo
// @var \Maatify\EventLogging\Common\ClockInterface $clock
// @var \Psr\Log\LoggerInterface $logger

$provider = EventLoggingProviderFactory::createDefault($pdo, $clock, $logger);
$deliveryOperations = $provider->deliveryOperations();

$command = new RecordDeliveryOperationCommand(
    channel: 'email',
    operationType: 'send_welcome',
    status: 'pending',
    attemptNo: 0,
    actorType: 'system',
    actorId: null,
    targetType: 'user',
    targetId: 42
);

$deliveryOperations->recordCommand($command);

echo "Recorded delivery operation event.\n";
