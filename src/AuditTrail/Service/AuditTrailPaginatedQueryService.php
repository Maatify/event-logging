<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\Service;

use Maatify\EventLogging\AuditTrail\Contract\AuditTrailPaginatedQueryInterface;
use Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryCursorDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryPageDTO;

class AuditTrailPaginatedQueryService implements AuditTrailPaginatedQueryInterface
{
    public function __construct(
        private readonly AuditTrailQueryInterface $repository
    ) {
    }

    public function findPage(AuditTrailQueryDTO $query): AuditTrailQueryPageDTO
    {
        if ($query->limit <= 0) {
            return new AuditTrailQueryPageDTO([], null, false);
        }

        // Create a new DTO for internal repository query asking for limit + 1
        $internalQuery = new AuditTrailQueryDTO(
            actorType: $query->actorType,
            actorId: $query->actorId,
            eventKey: $query->eventKey,
            entityType: $query->entityType,
            entityId: $query->entityId,
            subjectType: $query->subjectType,
            subjectId: $query->subjectId,
            requestId: $query->requestId,
            correlationId: $query->correlationId,
            after: $query->after,
            before: $query->before,
            cursorOccurredAt: $query->cursorOccurredAt,
            cursorId: $query->cursorId,
            limit: $query->limit + 1
        );

        $results = $this->repository->find($internalQuery);

        $hasMore = count($results) > $query->limit;

        if ($hasMore) {
            // Remove the extra item
            array_pop($results);
        }

        $nextCursor = null;
        if ($hasMore && count($results) > 0) {
            $lastKey = array_key_last($results);
            $lastItem = $results[$lastKey];
            $nextCursor = new AuditTrailQueryCursorDTO(
                occurredAt: $lastItem->occurredAt,
                id: $lastItem->id
            );
        }

        return new AuditTrailQueryPageDTO(
            items: array_values($results),
            nextCursor: $nextCursor,
            hasMore: $hasMore
        );
    }
}
