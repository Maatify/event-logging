<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use PDO;
use PDOException;
use PDOStatement;

class ThrowingPdo extends PDO
{
    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new PDOException('Simulated PDO connection/prepare error');
    }
}
