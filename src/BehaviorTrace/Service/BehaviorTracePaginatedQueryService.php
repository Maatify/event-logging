<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Service;

use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePaginatedQueryInterface;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceQueryInterface;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryCursorDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryDTO;
use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceQueryPageDTO;

class BehaviorTracePaginatedQueryService implements BehaviorTracePaginatedQueryInterface
{
    public function __construct(
        private readonly BehaviorTraceQueryInterface $repository
    ) {
    }

    public function findPage(BehaviorTraceQueryDTO $query): BehaviorTraceQueryPageDTO
    {
        if ($query->limit <= 0) {
            return new BehaviorTraceQueryPageDTO([], null, false);
        }

        $internalQuery = new BehaviorTraceQueryDTO(
            after: $query->after,
            before: $query->before,
            actorType: $query->actorType,
            actorId: $query->actorId,
            entityType: $query->entityType,
            entityId: $query->entityId,
            action: $query->action,
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
            $nextCursor = new BehaviorTraceQueryCursorDTO(
                occurredAt: $lastItem->context->occurredAt,
                id: $lastItem->id
            );
        }

        return new BehaviorTraceQueryPageDTO(
            items: array_values($results),
            nextCursor: $nextCursor,
            hasMore: $hasMore
        );
    }
}
