<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // Throw InvalidPaginationConfigurationException by setting an invalid page that hits the PageRequest exception.
        // Wait, PageRequest throws it in constructor if perPage is given but out of bounds?
        // Let\'s check PageRequest. Actually, PdoPaginator handles perPage logic.
        // If we want to test InvalidPaginationQueryException / InvalidPaginationConfigurationException...
        // Let\'s mock PDO properly for this test so we can trigger the sort column validation error.

        $stmtTotal = $this->createMock(PDOStatement::class);
        $pdo->method("prepare")->willReturn($stmtTotal);

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

        // PdoPaginator binds parameters! We must mock bindValue to return true.
        $stmtTotal->method("bindValue")->willReturn(true);
        $stmtFiltered->method("bindValue")->willReturn(true);
        $stmtData->method("bindValue")->willReturn(true);

        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("errorCode")->willReturn("00000");
        $stmtTotal->method("fetch")->willReturnOnConsecutiveCalls(["count" => 1], false);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("columnCount")->willReturn(1);
        $stmtFiltered->method("errorCode")->willReturn("00000");
        $stmtFiltered->method("fetch")->willReturnOnConsecutiveCalls(["count" => 1], false);

        $stmtData->method("execute")->willReturn(true);
        $stmtData->method("errorCode")->willReturn("00000");
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
