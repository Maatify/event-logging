<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use Psr\Log\AbstractLogger;

class SpyLogger extends AbstractLogger
{
    /** @var array<array{level: mixed, message: string, context: array<mixed>}> */
    public array $logs = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }
}
