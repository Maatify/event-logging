<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use PDO;
use PDOStatement;

class FakeStatement extends PDOStatement
{
    /** @var array<mixed> */
    public array $executedParams = [];

    /** @var array<mixed> */
    public array $fetchResults = [];

    public function execute(?array $params = null): bool
    {
        $this->executedParams = $params ?? [];
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->fetchResults;
    }
}
