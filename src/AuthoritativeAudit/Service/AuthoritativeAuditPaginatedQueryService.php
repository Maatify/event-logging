<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\Service;

use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditPaginatedQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryCursorDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryPageDTO;

class AuthoritativeAuditPaginatedQueryService implements AuthoritativeAuditPaginatedQueryInterface
{
    public function __construct(
        private readonly AuthoritativeAuditQueryInterface $repository
    ) {
    }

    public function findPage(AuthoritativeAuditQueryDTO $query): AuthoritativeAuditQueryPageDTO
    {
        if ($query->limit <= 0) {
            return new AuthoritativeAuditQueryPageDTO([], null, false);
        }

        $internalQuery = new AuthoritativeAuditQueryDTO(
            after: $query->after,
            before: $query->before,
            actorType: $query->actorType,
            actorId: $query->actorId,
            targetType: $query->targetType,
            targetId: $query->targetId,
            action: $query->action,
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
            $nextCursor = new AuthoritativeAuditQueryCursorDTO(
                occurredAt: $lastItem->occurredAt,
                id: $lastItem->id
            );
        }

        return new AuthoritativeAuditQueryPageDTO(
            items: array_values($results),
            nextCursor: $nextCursor,
            hasMore: $hasMore
        );
    }
}
