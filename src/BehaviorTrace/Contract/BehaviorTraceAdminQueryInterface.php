<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Contract;

use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminPageResultDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceAdminQueryRequestDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryExecutionException;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryInvalidArgumentException;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;

interface BehaviorTraceAdminQueryInterface
{
    /**
     * @throws BehaviorTraceAdminQueryInvalidArgumentException
     * @throws BehaviorTraceAdminQueryExecutionException
     * @throws BehaviorTraceStorageException
     */
    public function paginate(BehaviorTraceAdminQueryRequestDTO $request): BehaviorTraceAdminPageResultDTO;
}
