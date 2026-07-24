<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\DTO;

/**
 * @implements \IteratorAggregate<int, DeliveryOperationsViewDTO>
 */
final readonly class DeliveryOperationsAdminPageResultDTO implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @param list<DeliveryOperationsViewDTO> $items
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
    ) {}

    /**
     * @return \ArrayIterator<int, DeliveryOperationsViewDTO>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return array{
     *     items: list<DeliveryOperationsViewDTO>,
     *     page: int,
     *     perPage: int,
     *     total: int,
     *     filtered: int,
     *     totalPages: int,
     *     hasNext: bool,
     *     hasPrevious: bool,
     *     sortBy: string,
     *     sortDirection: string
     * }
     */
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
