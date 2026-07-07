<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Contract;

use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceCursorDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException;

interface BehaviorTraceQueryInterface
{
    /**
     * @return array<BehaviorTraceEventDTO>
     * @throws BehaviorTraceStorageException
     */
    public function find(BehaviorTraceQueryDTO $query): array;

    /**
     * @param BehaviorTraceCursorDTO|null $cursor
     * @param int $limit
     * @return iterable<BehaviorTraceEventDTO>
     */
    public function read(?BehaviorTraceCursorDTO $cursor, int $limit = 100): iterable;
}
