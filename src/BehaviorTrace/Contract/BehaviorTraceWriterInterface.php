<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\Contract;

use Maatify\EventLogging\BehaviorTrace\DTO\BehaviorTraceEventDTO;

interface BehaviorTraceWriterInterface
{
    public function write(BehaviorTraceEventDTO $dto): void;
}
