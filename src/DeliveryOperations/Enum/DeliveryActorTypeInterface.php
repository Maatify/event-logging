<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Enum;

interface DeliveryActorTypeInterface
{
    public function value(): string;
}
