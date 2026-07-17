<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // Throw InvalidPaginationConfigurationException by setting a totally invalid page limit via reflection if we can\'t mock paginator
        // Wait, PageRequest throws it immediately in constructor if perPage is out of range, e.g. < 1.
        // Actually, we can just pass an invalid sort property!
        // PaginationConfig only allows sortWhitelist = [occurred_at, id]
        // AuthoritativeAuditAdminQueryRequestDTO allows any string.
        // If we pass sortBy = "invalid", PdoPaginator throws InvalidPaginationQueryException during paginate()!

        // PdoPaginator executes exactly this in paginate():
        // $config->getSortWhitelist()->validate($request->getSortBy());
        // which throws InvalidPaginationQueryException!

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(sortBy: "invalid_sort_column"));
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void', $content);

$content = preg_replace('/    public function testExistingStorageExceptionIsNotRewrapped\(\): void.*$/s', '
    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);

        // We will execute a real PdoPaginator run, which executes 2 queries (total, filtered) and 1 data query.
        $stmtTotal = $this->createMock(PDOStatement::class);
        $stmtFiltered = $this->createMock(PDOStatement::class);
        $stmtData = $this->createMock(PDOStatement::class);

        $pdo->expects($this->exactly(3))
            ->method("prepare")
            ->willReturnOnConsecutiveCalls($stmtTotal, $stmtFiltered, $stmtData);

        // For count queries, PdoPaginator calls $stmt->fetch(PDO::FETCH_NUM) and gets [0 => count]
        $stmtTotal->expects($this->once())->method("execute")->willReturn(true);
        $stmtTotal->expects($this->once())->method("fetch")->willReturn([1]);

        $stmtFiltered->expects($this->once())->method("execute")->willReturn(true);
        $stmtFiltered->expects($this->once())->method("fetch")->willReturn([1]);

        // Data query returns a row that will cause RowMapper to fail
        $stmtData->expects($this->once())->method("execute")->willReturn(true);
        // We return one row with an invalid occurred_at. RowMapper will throw Exception,
        // which mapRow() will wrap in AuthoritativeAuditStorageException and throw.
        // paginate() must NOT rewrap this AuthoritativeAuditStorageException!
        $stmtData->expects($this->once())->method("fetchAll")->willReturn([
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
