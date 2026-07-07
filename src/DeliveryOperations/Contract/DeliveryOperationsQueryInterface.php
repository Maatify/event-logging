<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Contract;

use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsQueryDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsViewDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;

interface DeliveryOperationsQueryInterface
{
    /**
     * @return array<DeliveryOperationsViewDTO>
     * @throws DeliveryOperationsStorageException
     */
    public function find(DeliveryOperationsQueryDTO $query): array;
}
