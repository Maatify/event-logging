<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmtTotal = $this->createMock(PDOStatement::class);
        $stmtFiltered = $this->createMock(PDOStatement::class);
        $stmtData = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturnOnConsecutiveCalls($stmtTotal, $stmtFiltered, $stmtData);
        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("fetch")->willReturn(["count" => 1]); // Try named key
        $stmtTotal->method("columnCount")->willReturn(1);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("fetch")->willReturn(["count" => 1]);
        $stmtFiltered->method("columnCount")->willReturn(1);

        $stmtData->method("execute")->willReturn(true);
        $stmtData->method("fetchAll")->willReturn([]);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // Passing an invalid page number throws InvalidPaginationConfigurationException in PageRequest constructor before paginator starts!
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: -5));
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

        // PdoPaginator fetch using PDO::FETCH_NUM maybe?
        // Let\'s mock columnCount to 1, as that might be what PdoPaginator checks!
        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("fetch")->willReturn([1]);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("columnCount")->willReturn(1);
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
