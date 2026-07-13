<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Support;

use PDO;
use PDOStatement;

class FakeStatement extends PDOStatement
{
    /** @var array<mixed> */
    public array $executedParams = [];

    /** @var array<int, mixed> */
    public array $fetchResults = [];

    /** @param array<string|int, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        $this->executedParams = $params ?? [];
        return true;
    }

    /** @return array<int, mixed> */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->fetchResults;
    }
}
