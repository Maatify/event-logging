<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, SecuritySignalsViewDTO>
 */
final readonly class SecuritySignalsQueryPageDTO implements JsonSerializable, IteratorAggregate
{
    /**
     * @param list<SecuritySignalsViewDTO> $items
     */
    public function __construct(
        public array $items,
        public ?SecuritySignalsQueryCursorDTO $nextCursor,
        public bool $hasMore
    ) {
    }

    /**
     * @return Traversable<int, SecuritySignalsViewDTO>
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
