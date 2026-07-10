<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Contract;

use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryPageDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;

interface BehaviorTracePaginatedQueryInterface
{
    /**
     * @throws BehaviorTraceStorageException
     */
    public function findPage(BehaviorTraceQueryDTO $query): BehaviorTraceQueryPageDTO;
}
