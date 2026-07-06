<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Contract;

use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceCursorDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;

interface BehaviorTraceQueryInterface
{
    /**
     * @param BehaviorTraceCursorDTO|null $cursor
     * @param int $limit
     * @return iterable<BehaviorTraceEventDTO>
     */
    public function read(?BehaviorTraceCursorDTO $cursor, int $limit = 100): iterable;
}
