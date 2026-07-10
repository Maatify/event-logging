<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, AuthoritativeAuditViewDTO>
 */
final readonly class AuthoritativeAuditQueryPageDTO implements JsonSerializable, IteratorAggregate
{
    /**
     * @param list<AuthoritativeAuditViewDTO> $items
     */
    public function __construct(
        public array $items,
        public ?AuthoritativeAuditQueryCursorDTO $nextCursor,
        public bool $hasMore
    ) {
    }

    /**
     * @return Traversable<int, AuthoritativeAuditViewDTO>
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
