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
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("fetch")->willReturn([1]); // FETCH_NUM
        $stmtTotal->method("fetchAll")->willReturn([ [1] ]);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("columnCount")->willReturn(1);
        $stmtFiltered->method("fetch")->willReturn([1]);
        $stmtFiltered->method("fetchAll")->willReturn([ [1] ]);

        $stmtData->method("execute")->willReturn(true);
        $stmtData->method("fetchAll")->willReturn([
            ["valid_string_key" => 1] // Return associative array to avoid row keys must be strings error
        ]);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // This causes PageRequest to throw InvalidPaginationConfigurationException
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: -1));
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

        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("fetch")->willReturn([1]);
        $stmtTotal->method("fetchAll")->willReturn([ [1] ]);

        $stmtFiltered->method("execute")->willReturn(true);
        $stmtFiltered->method("columnCount")->willReturn(1);
        $stmtFiltered->method("fetch")->willReturn([1]);
        $stmtFiltered->method("fetchAll")->willReturn([ [1] ]);

        $stmtData->method("execute")->willReturn(true);
        // Has to be purely string keys!
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
