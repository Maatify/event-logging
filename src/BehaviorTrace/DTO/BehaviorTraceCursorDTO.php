<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\DTO;

use DateTimeImmutable;

final readonly class BehaviorTraceCursorDTO implements \JsonSerializable
{
    public function __construct(
        public DateTimeImmutable $lastOccurredAt,
        public int $lastId
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'lastOccurredAt' => $this->lastOccurredAt->format(DATE_ATOM),
            'lastId' => $this->lastId,
        ];
    }

}
