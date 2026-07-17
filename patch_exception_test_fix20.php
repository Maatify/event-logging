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

        // PdoPaginator throws InvalidPaginationQueryException when sort column is invalid (not in whitelist).
        // BUT wait, it throws it during paginate() before count query?
        // Ah, PdoPaginator normalize functions:
        // `resolveSortBy()` returns the default sort if it\'s invalid, it DOES NOT throw!
        // wait, PdoPaginator PaginationConfig constructor validates sort whitelist...
        // Let\'s just force PdoPaginator to throw it via reflection.

        $reflection = new ReflectionClass($repository);
        $paginatorProp = $reflection->getProperty("paginator");
        $paginatorProp->setAccessible(true);

        $mockPaginator = $this->createMock(\Maatify\Persistence\Pdo\Pagination\PdoPaginator::class);
        $mockPaginator->method("paginate")->willThrowException(new \Maatify\Persistence\Exception\InvalidPaginationQueryException("Test wrapper"));

        $paginatorProp->setValue($repository, $mockPaginator);

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
