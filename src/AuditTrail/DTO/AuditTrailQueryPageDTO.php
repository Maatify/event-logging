<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, AuditTrailViewDTO>
 */
final readonly class AuditTrailQueryPageDTO implements JsonSerializable, IteratorAggregate
{
    /**
     * @param list<AuditTrailViewDTO> $items
     */
    public function __construct(
        public array $items,
        public ?AuditTrailQueryCursorDTO $nextCursor,
        public bool $hasMore
    ) {
    }

    /**
     * @return Traversable<int, AuditTrailViewDTO>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'items' => $this->items,
            'nextCursor' => $this->nextCursor,
            'hasMore' => $this->hasMore,
        ];
    }
}
