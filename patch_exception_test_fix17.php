<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmtTotal = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturn($stmtTotal);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // PdoPaginator throws InvalidPaginationQueryException during validation of sort column.
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

        // PdoPaginator fetches count row and requires exactly one column.
        // And then fetchAssociativeOrEof uses FETCH_ASSOC.
        // So we MUST return ["count" => 1] and then `false` for EOF!
        // Because fetchAssociativeOrEof uses PDO::FETCH_ASSOC.
        // And PdoPaginator checks count($row) === 1.
        // And then it calls fetchAssociativeOrEof again and expects `false` to verify it\'s EOF.

        $stmtTotal->method("execute")->willReturn(true);
        $stmtTotal->method("columnCount")->willReturn(1);
        $stmtTotal->method("errorCode")->willReturn("00000"); // for false fetch
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
