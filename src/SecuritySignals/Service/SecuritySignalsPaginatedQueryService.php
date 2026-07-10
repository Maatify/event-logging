<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Service;

use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsPaginatedQueryInterface;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsQueryInterface;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryCursorDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryDTO;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsQueryPageDTO;

class SecuritySignalsPaginatedQueryService implements SecuritySignalsPaginatedQueryInterface
{
    public function __construct(
        private readonly SecuritySignalsQueryInterface $repository
    ) {
    }

    public function findPage(SecuritySignalsQueryDTO $query): SecuritySignalsQueryPageDTO
    {
        if ($query->limit <= 0) {
            return new SecuritySignalsQueryPageDTO([], null, false);
        }

        $internalQuery = new SecuritySignalsQueryDTO(
            after: $query->after,
            before: $query->before,
            actorType: $query->actorType,
            actorId: $query->actorId,
            signalType: $query->signalType,
            severity: $query->severity,
            requestId: $query->requestId,
            correlationId: $query->correlationId,
            cursorOccurredAt: $query->cursorOccurredAt,
            cursorId: $query->cursorId,
            limit: $query->limit + 1
        );

        $results = $this->repository->find($internalQuery);

        $hasMore = count($results) > $query->limit;

        if ($hasMore) {
            array_pop($results);
        }

        $nextCursor = null;
        if ($hasMore && count($results) > 0) {
            $lastKey = array_key_last($results);
            $lastItem = $results[$lastKey];
            $nextCursor = new SecuritySignalsQueryCursorDTO(
                occurredAt: $lastItem->occurredAt,
                id: $lastItem->id
            );
        }

        return new SecuritySignalsQueryPageDTO(
            items: array_values($results),
            nextCursor: $nextCursor,
            hasMore: $hasMore
        );
    }
}
