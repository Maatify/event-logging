<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\DTO;

use DateTimeImmutable;
use JsonSerializable;

final readonly class SecuritySignalsQueryCursorDTO implements JsonSerializable
{
    public function __construct(
        public DateTimeImmutable $occurredAt,
        public int $id
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
            'id' => $this->id,
        ];
    }
}
