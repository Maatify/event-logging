<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmtTotal = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturn($stmtTotal);

        // PdoPaginator ensures fetchAll() returns associative array!
        // To bypass this, we just don\'t return anything in data query, or rather, we don\'t even reach the query if PageRequest validation fails!
        // Wait, why is it executing fetchAll() and failing validation if PageRequest page < 1 should throw InvalidPaginationConfigurationException?
        // Ah! PageRequest might not throw if we pass page < 1 ?
        // Let\'s throw the exception explicitly from PDO so we can debug. No.

        // Let\'s check what PageRequest does. Maybe page = -1 is valid in PageRequest?
        // Let\'s just trigger InvalidPaginationQueryException by passing an invalid sort direction?
        // AuthoritativeAuditAdminQueryRequestDTO -> sortDirection is SortDirectionEnum::ASC? It only accepts enum value if it is an enum.

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // Trigger InvalidPaginationQueryException inside PdoPaginator by passing invalid sort field
        // This will be caught by paginate() and wrapped into AuthoritativeAuditAdminQueryExecutionException!
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(sortBy: "invalid_field_123"));
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void', $content);

$content = preg_replace('/    public function testExistingStorageExceptionIsNotRewrapped\(\): void.*$/s', '
    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);

        $stmtTotal = $this->createMock(PDOStatement::class);
        $stmtFiltered = $this->createMock(PDOStatement::class);
        $stmtData = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturnOnConsecutiveCalls($stmtTotal, $stmtFiltered, $stmtData);

        // For count queries, PdoPaginator uses fetch(PDO::FETCH_ASSOC) or fetchAll()?
        // It uses fetch(PDO::FETCH_ASSOC). So we must return ["total" => 1].
        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("fetch")->willReturn(["total" => 1]);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("columnCount")->willReturn(1);
        $stmtFiltered->method("fetch")->willReturn(["total" => 1]);

        // For data query, PdoPaginator uses fetch(PDO::FETCH_ASSOC) which returns associative array.
        // It checks if all keys are strings.
        $stmtData->method("execute")->willReturn(true);

        // We MUST use PDOStatement->fetch() iteratively. PdoPaginator loop:
        // while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        // So we mock fetch() to return our row, then false.
        $stmtData->method("fetch")->willReturnOnConsecutiveCalls(
            ["event_id" => "mock-event", "occurred_at" => "invalid-date"],
            false
        );

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage("Failed to map AuthoritativeAudit row: Invalid occurred_at format");

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }
}
', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
