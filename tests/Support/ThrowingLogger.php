<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use Psr\Log\AbstractLogger;
use RuntimeException;

class ThrowingLogger extends AbstractLogger
{
    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        throw new RuntimeException('Fallback logger failed');
    }
}
