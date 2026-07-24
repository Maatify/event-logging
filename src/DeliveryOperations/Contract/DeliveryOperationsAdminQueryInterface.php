<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Contract;

use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminPageResultDTO;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryExecutionException;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;

interface DeliveryOperationsAdminQueryInterface
{
    /**
     * @throws DeliveryOperationsStorageException
     * @throws DeliveryOperationsAdminQueryExecutionException
     * @throws DeliveryOperationsAdminQueryInvalidArgumentException
     */
    public function paginate(DeliveryOperationsAdminQueryRequestDTO $request): DeliveryOperationsAdminPageResultDTO;
}
