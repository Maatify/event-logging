<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use PDO;
use PDOException;
use PDOStatement;

class ThrowingStatement extends PDOStatement
{
    /** @param array<string|int, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        throw new PDOException('Simulated execution error');
    }
}

class ThrowingStatementPdo extends PDO
{
    public function __construct()
    {
    }

    /** @param array<int, mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new ThrowingStatement();
    }
}
