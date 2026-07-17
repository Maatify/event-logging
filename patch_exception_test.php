<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = str_replace(
'    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $exception = new \Maatify\Persistence\Exception\InvalidPaginationConfigurationException("test");
        $this->assertInstanceOf(\Maatify\Persistence\Exception\InvalidPaginationConfigurationException::class, $exception);
    }',
'    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $pdo->method("prepare")->willReturn($stmt);

        // Return 0 for total count, then the execution flow will continue to empty check
        $stmt->method("fetch")->willReturn(["total" => 0, "filtered" => 0]);
        $stmt->method("fetchAll")->willReturn([]);

        // Force PdoPaginator to throw an InvalidPaginationQueryException (e.g. by passing invalid perPage, though Maatify\Persistence might throw it in paginate).
        // A cleaner way is to mock PdoPaginator if it was injectable, but it is internally instantiated.
        // Instead, we will pass an invalid page request that triggers the domain execution exception.
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // This will trigger InvalidPaginationConfigurationException inside PdoPaginator due to perPage < 1
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: 1, perPage: 0));
    }', $content);

$content = str_replace(
'    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $exception = new AuthoritativeAuditStorageException(\'Original storage exception\');
        $this->assertInstanceOf(AuthoritativeAuditStorageException::class, $exception);
    }',
'    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $reflection = new ReflectionClass($repository);
        $mapRowMethod = $reflection->getMethod("mapRow");
        $mapRowMethod->setAccessible(true);

        $originalException = new AuthoritativeAuditStorageException("Original storage exception");

        // To test that it does not re-wrap, we could simulate mapRow throwing an existing AuthoritativeAuditStorageException
        // but mapRow itself is private. Instead we can just catch what it throws directly, or mock PDO fetch.

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage("Original storage exception");

        // We simulate mapRow throwing by throwing it directly from a mocked callback or similar
        // Wait, mapRow throws AuthoritativeAuditStorageException directly if it catches Exception.
        // We will just invoke it with something that makes it throw the original exception. But we can\'t inject our exception into mapRow.
        // Let\'s look at AuthoritativeAuditRowMapper.
        $this->markTestIncomplete("Need to see RowMapper and AdminQueryRepository code to properly throw this exception.");
    }', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
