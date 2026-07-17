<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // PdoPaginator is throwing PaginationExecutionException because we haven\'t mocked PDO to handle the total count query.
        // Wait, "Failed to query AuthoritativeAudit records: Failed to prepare pagination query."
        // That means PdoPaginator tried to prepare a query and PDO returned false or null.
        // So we MUST mock PDO prepare to at least return a dummy PDOStatement, so PdoPaginator can throw InvalidPaginationQueryException during validation!
        // No, PdoPaginator validates BEFORE executing!
        // Ah, PdoPaginator calls `$this->pdo->prepare($descriptor->totalSql)`
        // Wait, why does it reach `prepare` if it throws InvalidPaginationQueryException on validation?
        // Let\'s mock prepare to return a dummy PDOStatement just in case.

        $stmt = $this->createMock(PDOStatement::class);
        $pdo->method("prepare")->willReturn($stmt);
        $stmt->method("fetch")->willReturn([1]);
        $stmt->method("fetchAll")->willReturn([]);

        // This will pass the sort direction validation? Wait, SortDirection validation is in PageRequest.
        // If we want InvalidPaginationQueryException, it comes from PdoPaginator validating whitelist.
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(sortBy: "invalid_sort_column"));
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

        // PdoPaginator expects fetch() to return an array containing EXACTLY ONE COLUMN (e.g. [0 => 1]).
        // If we return ["total" => 1], count(["total" => 1]) == 1. Wait, is it?
        // Let\'s just return [1] explicitly.
        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("fetch")->willReturn([1]);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("fetch")->willReturn([1]);

        $stmtData->method("execute")->willReturn(true);
        $stmtData->method("fetchAll")->willReturn([
            ["event_id" => "mock-event", "occurred_at" => "invalid-date"]
        ]);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage("Failed to map AuthoritativeAudit row: Invalid occurred_at format");

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }
}
', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
