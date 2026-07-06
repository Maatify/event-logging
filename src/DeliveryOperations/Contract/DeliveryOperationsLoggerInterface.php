<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Contract;

use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;

interface DeliveryOperationsLoggerInterface
{
    public function log(DeliveryOperationRecordDTO $dto): void;
}
