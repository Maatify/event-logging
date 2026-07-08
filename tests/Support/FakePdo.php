<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use PDO;
use PDOStatement;

class FakePdo extends PDO
{
    /** @var array<string, FakeStatement> */
    public array $statements = [];
    public ?FakeStatement $lastStatement = null;

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $stmt = new FakeStatement();
        $stmt->queryString = $query;
        $this->statements[$query] = $stmt;
        $this->lastStatement = $stmt;
        return $stmt;
    }
}
