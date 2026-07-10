<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, BehaviorTraceEventDTO>
 */
final readonly class BehaviorTraceQueryPageDTO implements JsonSerializable, IteratorAggregate
{
    /**
     * @param list<BehaviorTraceEventDTO> $items
     */
    public function __construct(
        public array $items,
        public ?BehaviorTraceQueryCursorDTO $nextCursor,
        public bool $hasMore
    ) {
    }

    /**
     * @return Traversable<int, BehaviorTraceEventDTO>
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
