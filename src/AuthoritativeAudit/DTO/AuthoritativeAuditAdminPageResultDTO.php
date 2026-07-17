<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * @implements IteratorAggregate<int, AuthoritativeAuditViewDTO>
 */
final readonly class AuthoritativeAuditAdminPageResultDTO implements IteratorAggregate, JsonSerializable
{
    /**
     * @param list<AuthoritativeAuditViewDTO> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
        public int $filtered,
        public int $totalPages,
        public bool $hasNext,
        public bool $hasPrevious,
        public string $sortBy,
        public string $sortDirection
    ) {
    }

    /** @return ArrayIterator<int, AuthoritativeAuditViewDTO> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'total' => $this->total,
            'filtered' => $this->filtered,
            'totalPages' => $this->totalPages,
            'hasNext' => $this->hasNext,
            'hasPrevious' => $this->hasPrevious,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ];
    }
}
